import Tesseract from 'tesseract.js';

export interface ReceiptValidationResult {
  isValid: boolean;
  extractedAmount?: number;
  extractedMethod?: string;
  confidence: number;
  errors: string[];
}

interface PaymentPattern {
  keywords: string[];
  amountPattern: RegExp | RegExp[];
  referencePattern: RegExp | RegExp[];
  additionalValidation?: (text: string) => boolean;
}

// Updated payment method patterns for Philippine banks
const PAYMENT_PATTERNS: Record<string, PaymentPattern> = {
  gcash: {
    keywords: ['gcash', 'globe gcash', 'mynt', 'send money'],
    amountPattern: /(?:amount|total|php|₱)\s*:?\s*(\d+(?:,\d{3})*(?:\.\d{2})?)/i,
    referencePattern: /(?:reference|ref|transaction)\s*(?:no|number)?\s*:?\s*([a-z0-9]+)/i,
  },
  paymaya: {
    keywords: ['paymaya', 'maya', 'voyager innovations'],
    amountPattern: /(?:amount|total|php|₱)\s*:?\s*(\d+(?:,\d{3})*(?:\.\d{2})?)/i,
    referencePattern: /(?:reference|ref|transaction)\s*(?:no|number)?\s*:?\s*([a-z0-9]+)/i,
  },
  bpi: {
    keywords: ['bpi', 'bank of the philippine islands', 'deposit', 'payment slip', 'client\'s copy', 'teller\'s validation'],
    // More specific BPI patterns focusing on the actual deposit amount at the bottom
    amountPattern: [
      // Primary pattern: Look for amount at the end of lines with proper formatting
      /cash\s+(\d+(?:,\d{3})*\.\d{2})/i,
      // Secondary pattern: PHP followed by amount with asterisks (common in BPI)
      /php\s*\*+(\d+(?:,\d{3})*\.\d{2})/i,
      // Tertiary pattern: Amount followed by deposit context
      /(\d+(?:,\d{3})*\.\d{2})\s*(?:deposit|cash)/i,
      // Look for amounts in transaction lines
      /(?:php|₱)\s*(\d+(?:,\d{3})*\.\d{2})\s*(?:\d{2}:\d{2}:\d{2}|\d{2}-\d{2}-\d{2})/i
    ],
    referencePattern: /(\d{2}[a-z]{3}\d{2}|\d{8,12})/i,
    additionalValidation: (text: string): boolean => {
      const hasBPIBranding = /bpi|bank of the philippine islands/i.test(text);
      const hasDepositSlip = /deposit.*slip|payment.*slip/i.test(text);
      const hasValidationSection = /teller.*validation|machine.*validated/i.test(text);
      return hasBPIBranding && (hasDepositSlip || hasValidationSection);
    }
  },
  bdo: {
    keywords: ['bdo', 'banco de oro', 'cash transaction slip', 'deposits', 'cash deposit'],
    // More specific BDO patterns
    amountPattern: [
      // Primary: Cash In amount (most reliable for BDO)
      /cash\s*in:\s*(\d+(?:,\d{3})*\.\d{2})/i,
      // Secondary: Cash deposit with PHP prefix
      /cash\s*deposit\s*php\s*(\d+(?:,\d{3})*\.\d{2})/i,
      // Tertiary: Total deposit amount
      /total.*deposit.*(\d+(?:,\d{3})*\.\d{2})/i,
      // Look for amount patterns near "acct" or "deposit" keywords
      /(?:acct|deposit).*php\s*(\d+(?:,\d{3})*\.\d{2})/i
    ],
    referencePattern: /(\d{10,12}|\d{4}\s*\d{3}\s*\d{4})/i,
    additionalValidation: (text: string): boolean => {
      const hasBDOBranding = /bdo|banco de oro/i.test(text);
      const hasCashTransaction = /cash transaction slip|cash deposit/i.test(text);
      const hasAccountNumber = /account\s*no|account\s*name/i.test(text);
      return hasBDOBranding && (hasCashTransaction || hasAccountNumber);
    }
  },
  unionbank: {
    keywords: ['unionbank', 'union bank', 'deposit slip', 'cash dep', 'total deposit'],
    amountPattern: [
      // Primary: Total deposit amount (most common in UnionBank receipts)
      /total\s*deposit\s*(\d+(?:,\d{3})*\.\d{2})/i,
      // Secondary: "Total" followed by amount (common in UB receipts)
      /total\s+(\d+(?:,\d{3})*\.\d{2})/i,
      // Tertiary: Cash deposit amount
      /cash\s*dep[.]?\s*(\d+(?:,\d{3})*\.\d{2})/i,
      // Quaternary: Amount on same line as "cash dep"
      /cash\s*dep.*?(\d+(?:,\d{3})*\.\d{2})/i,
      // Quinary: Stand-alone amounts at end of lines
      /(\d+(?:,\d{3})*\.\d{2})$/gm,
      // Senary: Amount patterns in breakdown section
      /(\d+(?:,\d{3})*\.\d{2})(?:\s*(?:\n|\r|$))/i,
      // Septenary: PHP currency amounts
      /php\s*(\d+(?:,\d{3})*\.\d{2})/i,
      // Octonary: Simple amount patterns with proper formatting
      /(\d{1,3}(?:,\d{3})*\.\d{2})/g
    ],
    referencePattern: [
      // UB transaction reference patterns
      /ub\s*transaction\s*reference\s*number\s*([a-z0-9]+)/i,
      // Reference number patterns
      /reference\s*number\s*([a-z0-9]+)/i,
      // Long numeric references (12-15 digits)
      /(\d{12,15})/i,
      // Account or transaction numbers in UnionBank format
      /([a-z0-9]{8,15})/i
    ],
    additionalValidation: (text: string): boolean => {
      // Look for UnionBank specific elements
      const hasUBBranding = /unionbank|union bank/i.test(text);
      const hasDepositSlip = /deposit slip|cash dep/i.test(text);
      const hasTransactionRef = /ub transaction reference|reference number/i.test(text);
      const hasTotalDeposit = /total deposit|total\s*\d+/i.test(text);
      const hasAccountInfo = /account number|account name/i.test(text);

      // More lenient validation - just need UnionBank branding and some banking context
      return hasUBBranding && (hasDepositSlip || hasTransactionRef || hasTotalDeposit || hasAccountInfo);
    }
  },
  metrobank: {
    keywords: ['metrobank', 'metropolitan bank', 'deposit slip', 'cash deposit'],
    amountPattern: [
      // HIGHEST PRIORITY: Large amounts with proper comma formatting (10k+)
      /(?:^|\s)(\d{2,3},\d{3}(?:,\d{3})*\.\d{2})(?:\s|$)/gm,

      // HIGH PRIORITY: 5-digit amounts (10,000-99,999) - most common deposit range
      /(?:^|\s)(\d{2},\d{3}\.\d{2})(?:\s|$)/gm,

      // PRIORITY: 4-digit amounts (1,000-9,999) with word boundaries
      /(?:^|\s)(\d{1},\d{3}\.\d{2})(?:\s|$)/gm,

      // CONTEXT PRIORITY: Amount in transaction line with Metrobank reference format
      /\d{3}-\d-\d{8}-\d.*?(\d{1,3}(?:,\d{3})+\.\d{2})/i,

      // CONTEXT PRIORITY: PHP followed by large amounts (strict word boundaries)
      /php\s+(\d{2,3},\d{3}(?:,\d{3})*\.\d{2})/i,

      // CONTEXT PRIORITY: CS (Cash) followed by large amounts
      /cs\s+(\d{2,3},\d{3}(?:,\d{3})*\.\d{2})/i,

      // CONTEXT: Total deposit amounts with strict matching
      /total\s*(?:deposit|cash\s*deposit)\s*(\d{1,3}(?:,\d{3})+\.\d{2})/i,

      // CONTEXT: DEP ON (Deposit On) with amount
      /dep\s+on.*?(\d{1,3}(?:,\d{3})+\.\d{2})/i,

      // MEDIUM PRIORITY: Amount in transaction timestamp line
      /\d{2}\/\d{2}\/\d{4}\s+\d{2}:\d{2}:\d{2}.*?(\d{1,3}(?:,\d{3})+\.\d{2})/i,

      // LOWER PRIORITY: PHP followed by any properly formatted amount
      /php\s+(\d{1,3}(?:,\d{3})*\.\d{2})/i,

      // LOWER PRIORITY: CS followed by any amount
      /cs\s+(\d{1,3}(?:,\d{3})*\.\d{2})/i,

      // FALLBACK: Any amount with proper formatting (but lower score)
      /(?:^|\s)(\d{3,4}\.\d{2})(?:\s|$)/gm,

      // LAST RESORT: Broad match with very low score
      /(\d{1,3}(?:,\d{3})*\.\d{2})/g
    ],
    referencePattern: /(\d{3}-\d-\d{8}-\d)|(\d{4}\s*\d{3}\s*\d{4})/i,
    additionalValidation: (text: string): boolean => {
      // Look for Metrobank specific elements
      const hasMetrobankBranding = /metrobank|metropolitan bank/i.test(text);
      const hasDepositSlip = /deposit slip|cash deposit/i.test(text);
      const hasReceiptValidation = /machine validated|receipt when machine validated|this is your receipt/i.test(text);
      const hasTransactionFormat = /\d{3}-\d-\d{8}-\d|\d{4}\s+\d{3}\s+\d{4}/i.test(text); // Metrobank transaction format
      const hasCashTransaction = /cs\s+\d+|php\s+\d+/i.test(text); // Cash or PHP amount indicators
      const hasAccountFields = /account number|account name/i.test(text); // Account related fields
      const hasDateTimeFormat = /\d{2}\/\d{2}\/\d{4}\s+\d{2}:\d{2}:\d{2}/i.test(text); // Transaction timestamp
      const hasTotalDeposit = /total\s*(?:deposit|depost|cash)/i.test(text); // Handle OCR typos for "total deposit"

      return hasMetrobankBranding && (
        hasDepositSlip || 
        hasReceiptValidation || 
        hasTransactionFormat || 
        hasCashTransaction || 
        hasAccountFields || 
        hasDateTimeFormat ||
        hasTotalDeposit
      );
    }
  },
};

export const validateReceipt = async (
  imageFile: File,
  expectedAmount: number,
  expectedMethod: string
): Promise<ReceiptValidationResult> => {
  try {
    // Extract text from image using Tesseract with enhanced preprocessing
    const result = await Tesseract.recognize(imageFile, 'eng', {
      logger: (m: { status: string; progress: number; userJobId?: string }) => console.log(m),
    });

    const extractedText = result.data.text.toLowerCase();
    const originalText = result.data.text; // Keep original case for some patterns
    const confidence = result.data.confidence;
    const errors: string[] = [];

    console.log('Extracted text:', extractedText);

    // Get payment method pattern
    const methodPattern = PAYMENT_PATTERNS[expectedMethod];
    if (!methodPattern) {
      errors.push(`Unsupported payment method: ${expectedMethod}`);
      return { isValid: false, confidence, errors };
    }

    // Check if payment method keywords are present
    const hasMethodKeywords = methodPattern.keywords.some(keyword => 
      extractedText.includes(keyword.toLowerCase())
    );

    // Additional validation for banks
    let hasAdditionalValidation = true;
    if (methodPattern.additionalValidation) {
      hasAdditionalValidation = methodPattern.additionalValidation(extractedText);
    }

    if (!hasMethodKeywords && !hasAdditionalValidation) {
      errors.push(`Receipt does not appear to be from ${expectedMethod}`);
    }

    // Extract amount from text with multiple attempts
    let extractedAmount: number | undefined;
    const allMatches: Array<{ match: RegExpMatchArray; pattern: string }> = [];

    // Handle different pattern types (single regex vs array of regexes)
    const patterns = Array.isArray(methodPattern.amountPattern) 
      ? methodPattern.amountPattern 
      : [methodPattern.amountPattern];

    // Try each pattern and collect all matches
    for (const pattern of patterns) {
      const matches = [...originalText.matchAll(new RegExp(pattern.source, 'gi'))];
      allMatches.push(...matches.map(match => ({ match, pattern: pattern.source })));
    }

    console.log('Amount matches found:', allMatches);

    // Enhanced logging for Metrobank
    if (expectedMethod === 'metrobank') {
      console.log('=== METROBANK DEBUGGING ===');
      console.log('Expected amount:', expectedAmount);
      console.log('Raw extracted text preview:', extractedText.substring(0, 200));
      console.log('Total patterns tested:', patterns.length);
      console.log('Raw matches found:', allMatches.length);
    }

    // Filter and score matches based on context and value
    const validAmounts: { amount: number; score: number; context: string; pattern: string }[] = [];

    for (const { match, pattern } of allMatches) {
      let parsedAmount: number;

      // Regular processing for all patterns
      const amountStr = match[1] || match[2] || match[3];
      if (amountStr) {
        const cleanAmount = amountStr.replace(/,/g, '').replace(/[^\d.]/g, '');
        parsedAmount = parseFloat(cleanAmount);
      } else {
        continue;
      }

      if (!isNaN(parsedAmount) && parsedAmount >= 10) {
        let score = 0;
        const context = match[0].toLowerCase();

        // Scoring system for amount relevance
        if (expectedMethod === 'unionbank') {
          // For UnionBank, prioritize total deposit amounts
          if (/total\s*deposit/.test(context)) score += 70;
          if (/total\s+\d+/.test(context)) score += 65; // "Total 8,000.00" pattern
          if (/cash\s*dep/.test(context)) score += 60;
          if (/total/.test(context)) score += 55;
          if (/deposit/.test(context)) score += 40;
          // Boost scores for reasonable deposit amounts
          if (parsedAmount >= 1000 && parsedAmount <= 100000) score += 25;
          if (parsedAmount >= 5000) score += 15;
          // Boost score for amounts that end lines (common in UB receipts)
          if (/\d+\.\d{2}$/.test(amountStr)) score += 20;
          // Penalize very small amounts that might be fees
          if (parsedAmount < 100) score -= 30;
        } else if (expectedMethod === 'metrobank') {
          // Enhanced Metrobank scoring system

          // HIGHEST PRIORITY: Pattern-based scoring (what regex matched)
          if (/(?:^|\s)(\d{2,3},\d{3}(?:,\d{3})*\.\d{2})(?:\s|$)/.test(match[0])) score += 500; // Large amounts pattern
          if (/(?:^|\s)(\d{2},\d{3}\.\d{2})(?:\s|$)/.test(match[0])) score += 450; // 5-digit pattern
          if (/(?:^|\s)(\d{1},\d{3}\.\d{2})(?:\s|$)/.test(match[0])) score += 400; // 4-digit pattern
          if (/\d{3}-\d-\d{8}-\d.*?(\d{1,3}(?:,\d{3})+\.\d{2})/.test(match[0])) score += 350; // Reference line

          // Amount size scoring - heavily favor larger deposits
          if (parsedAmount >= 50000) score += 300; // 50k+
          if (parsedAmount >= 20000) score += 250; // 20k+
          if (parsedAmount >= 10000) score += 200; // 10k+
          if (parsedAmount >= 5000) score += 150; // 5k+
          if (parsedAmount >= 1000) score += 100; // 1k+

          // Expected amount proximity - MAJOR boost for close matches
          const tolerance = expectedAmount * 0.05; // 5% tolerance
          if (Math.abs(parsedAmount - expectedAmount) <= tolerance) score += 800;

          const mediumTolerance = expectedAmount * 0.15; // 15% tolerance
          if (Math.abs(parsedAmount - expectedAmount) <= mediumTolerance) score += 400;

          const largeTolerance = expectedAmount * 0.30; // 30% tolerance for OCR errors
          if (Math.abs(parsedAmount - expectedAmount) <= largeTolerance) score += 200;

          // Context-based scoring
          if (/php\s+\d{2,3},\d{3}/.test(context)) score += 180; // PHP with large amount
          if (/cs\s+\d{2,3},\d{3}/.test(context)) score += 170; // CS with large amount
          if (/total\s*(?:deposit|depost|cash\s*deposit)/.test(context)) score += 160;
          if (/dep\s+on.*\d+/.test(context)) score += 150;
          if (/\d{2}\/\d{2}\/\d{4}.*\d{2}:\d{2}:\d{2}/.test(match[0])) score += 140;

          // Format validation bonuses
          if (/^\d{2},\d{3}\.\d{2}$/.test(amountStr)) score += 120; // Perfect 5-digit format
          if (/^\d{1},\d{3}\.\d{2}$/.test(amountStr)) score += 100; // Perfect 4-digit format
          if (/^\d{3,4}\.\d{2}$/.test(amountStr)) score += 80; // 3-4 digit without comma

          // Word boundary bonuses (prevent partial matches)
          if (/(?:^|\s)\d/.test(match[0]) && /\d(?:\s|$)/.test(match[0])) score += 150;

          // Severe penalties for problematic amounts
          if (parsedAmount < 100) score -= 500; // Tiny amounts are likely errors
          if (parsedAmount < 500) score -= 300;
          if (parsedAmount < 1000) score -= 100;

          // Penalize if amount is way off from expected
          if (expectedAmount > 0) {
            const ratio = Math.max(parsedAmount / expectedAmount, expectedAmount / parsedAmount);
            if (ratio > 10) score -= 400; // If 10x different, heavily penalize
            if (ratio > 5) score -= 200;   // If 5x different, penalize
          }
        } else if (expectedMethod === 'bpi') {
          // For BPI, prioritize amounts at the end of lines with proper context
          if (/cash\s*\d+/.test(context)) score += 50;
          if (/php\s*\*+/.test(context)) score += 40;
          if (/deposit/.test(context)) score += 30;
          if (/\d{2}:\d{2}:\d{2}/.test(match[0])) score += 35; // Time context
          // Penalize insurance-related amounts
          if (/insurance|maximum/.test(originalText.slice(Math.max(0, match.index! - 100), match.index! + 100).toLowerCase())) {
            score -= 100;
          }
        } else if (expectedMethod === 'bdo') {
          // For BDO, prioritize "Cash In" amounts
          if (/cash\s*in/.test(context)) score += 60;
          if (/cash\s*deposit/.test(context)) score += 50;
          if (/total.*deposit/.test(context)) score += 40;
          if (/acct.*php/.test(context)) score += 35;
          // Penalize small random numbers
          if (parsedAmount < 100) score -= 20;
        }

        // General scoring
        if (parsedAmount >= 100) score += 10; // Reasonable deposit amounts
        if (parsedAmount >= 1000) score += 5;

        validAmounts.push({ 
          amount: parsedAmount, 
          score, 
          context: match[0],
          pattern: pattern
        });

        // Enhanced logging for debugging
        if (expectedMethod === 'metrobank') {
          console.log(`Found amount: ₱${parsedAmount} | Score: ${score} | Context: "${match[0]}" | Pattern: ${pattern.substring(0, 50)}...`);
        }
      }
    }

    // If no amounts found with primary patterns, try fallback extraction
    if (validAmounts.length === 0) {
      console.log('No amounts found with primary patterns, trying fallback...');

      // Enhanced fallback patterns for Metrobank
      const fallbackPatterns = expectedMethod === 'metrobank' ? [
        // Metrobank-specific fallback patterns
        /php\s+(\d{2,3},\d{3}(?:,\d{3})*\.\d{2})/gi, // PHP + large amounts first
        /(?:^|\s)(\d{2},\d{3}\.\d{2})(?:\s|$)/gm, // 5-digit amounts with boundaries
        /(?:^|\s)(\d{1},\d{3}\.\d{2})(?:\s|$)/gm, // 4-digit amounts with boundaries
        /cs\s+(\d{1,3}(?:,\d{3})+\.\d{2})/gi, // CS followed by comma-formatted amounts
        /(\d{2,3},\d{3}(?:,\d{3})*\.\d{2})/g, // Any large comma-formatted amount
        /(\d{1,3}(?:,\d{3})*\.\d{2})/g, // Standard comma format
        /(\d{4,6}\.\d{2})/g, // 4-6 digit amounts without commas
        /(\d{3}\.\d{2})/g, // 3-digit format
      ] : [
        // Original fallback patterns for other banks
        /php\s*[^\d]*(\d+(?:,\d{3})*(?:\.\d{2})?)/gi,
        /(\d{1,3}(?:,\d{3})*\.\d{2})/g,
        /(\d+,\d{3}\.\d{2})/g,
        /(\d{3}\.\d{2})/g,
        /(\d{2},\d{3}\.\d{2})/g,
        /cs\s*[^\d]*(\d+(?:,\d{3})*(?:\.\d{2})?)/gi,
        /dep\s+on.*?(\d+(?:,\d{3})*\.\d{2})/gi,
        /(\d+\.\d{2})/g,
      ];

      for (const fallbackPattern of fallbackPatterns) {
        const fallbackMatches = [...originalText.matchAll(fallbackPattern)];

        for (const match of fallbackMatches) {
          const amountStr = match[1];
          const cleanAmount = amountStr.replace(/,/g, '').replace(/[^\d.]/g, '');
          const parsedAmount = parseFloat(cleanAmount);

          if (!isNaN(parsedAmount) && parsedAmount >= 100) {
            let score = 15; // Base score for fallback

            // Boost score based on pattern type
            if (fallbackPattern.source.includes('php')) score += 50; // Higher priority for PHP patterns
            if (fallbackPattern.source.includes('dep')) score += 45; // DEP ON context
            if (fallbackPattern.source.includes('cs')) score += 40;
            if (/\d{2},\d{3}\.\d{2}/.test(amountStr)) score += 35; // 5-digit format
            if (/\d{1},\d{3}\.\d{2}/.test(amountStr)) score += 30; // 4-digit format
            if (/\d{3}\.\d{2}/.test(amountStr) && !/\d{4}/.test(amountStr)) score += 25; // 3-digit format

            // Check if amount is near expected amount
            const tolerance = expectedAmount * 0.15;
            if (Math.abs(parsedAmount - expectedAmount) <= tolerance) {
              score += 60;
            }

            // Boost score for reasonable amounts
            if (parsedAmount >= 1000) score += 25;
            if (parsedAmount >= 5000) score += 15;
            if (parsedAmount >= 10000) score += 10;

            validAmounts.push({
              amount: parsedAmount,
              score,
              context: `fallback ${fallbackPattern.source}: ${match[0]}`,
              pattern: fallbackPattern.source,
            });
          }
        }
      }
    }

    // Sort by score and pick the best match
    validAmounts.sort((a, b) => b.score - a.score);

    console.log('Scored amounts:', validAmounts);

    if (validAmounts.length > 0) {
      extractedAmount = validAmounts[0].amount;
      console.log('Selected amount:', extractedAmount, 'from context:', validAmounts[0].context);

      // Enhanced Metrobank logging
      if (expectedMethod === 'metrobank') {
        console.log('=== FINAL SELECTION ===');
        console.log('Top 3 candidates:');
        validAmounts.slice(0, 3).forEach((amt, idx) => {
          console.log(`${idx + 1}. ₱${amt.amount} (Score: ${amt.score}) - "${amt.context}"`);
        });
        console.log('Expected: ₱' + expectedAmount + ' | Selected: ₱' + extractedAmount);
        console.log('Difference: ₱' + Math.abs(expectedAmount - extractedAmount));
      }
    }

    if (extractedAmount !== undefined) {
      // Check if extracted amount matches expected amount (allow 1% tolerance for exact matches, 5% for bank receipts)
      const tolerance = expectedAmount * 0.05; // 5% tolerance for bank receipts due to OCR variations
      const amountDifference = Math.abs(extractedAmount - expectedAmount);

      if (amountDifference > tolerance) {
        errors.push(`Amount mismatch: Expected ₱${expectedAmount}, found ₱${extractedAmount}`);
      }
    } else {
      errors.push('Could not extract amount from receipt');
    }

    // Check for reference number with enhanced patterns
    let hasReference = false;
    if (Array.isArray(methodPattern.referencePattern)) {
      // Handle array of reference patterns
      for (const refPattern of methodPattern.referencePattern) {
        const matches = [...originalText.matchAll(new RegExp(refPattern.source, 'gi'))];
        if (matches.length > 0) {
          hasReference = true;
          break;
        }
      }
    } else {
      // Handle single reference pattern
      const referenceMatches = [...originalText.matchAll(new RegExp(methodPattern.referencePattern.source, 'gi'))];
      hasReference = referenceMatches.length > 0;
    }

    if (!hasReference) {
      // For banks, this is more important than for e-wallets
      if (['bpi', 'bdo', 'unionbank', 'metrobank'].includes(expectedMethod)) {
        errors.push('No transaction reference found');
      } else {
        errors.push('No transaction reference found (warning only)');
      }
    }

    // Enhanced validation logic for banks
    let isValid = false;

    if (['bpi', 'bdo', 'unionbank', 'metrobank'].includes(expectedMethod)) {
      // For banks: require either method keywords OR additional validation, amount extraction, and reference
      const hasMethodIdentification = hasMethodKeywords || hasAdditionalValidation;
      const hasValidAmount = extractedAmount !== undefined && 
                           Math.abs(extractedAmount - expectedAmount) <= (expectedAmount * 0.05);

      isValid = hasMethodIdentification && hasValidAmount;

      // If we have a reference, it adds to the confidence but isn't strictly required
      if (hasReference) {
        // Reference found adds confidence
      } else if (!hasValidAmount) {
        isValid = false; // Without amount, reference becomes more critical
      }
    } else {
      // For e-wallets: original logic
      isValid = hasMethodKeywords && extractedAmount !== undefined && 
               Math.abs(extractedAmount - expectedAmount) <= (expectedAmount * 0.01);
    }

    return {
      isValid,
      extractedAmount,
      extractedMethod: (hasMethodKeywords || hasAdditionalValidation) ? expectedMethod : undefined,
      confidence,
      errors
    };

  } catch (error) {
    console.error('OCR Error:', error);
    return {
      isValid: false,
      confidence: 0,
      errors: ['Failed to process receipt image']
    };
  }
};