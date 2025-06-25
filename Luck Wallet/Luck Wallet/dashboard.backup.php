<?php
require_once __DIR__ . '/php/session_handler.php';
$currentUser = getCurrentUser();

// Redirect to login if not logged in
if (!$currentUser) {
    header('Location: /auth/login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Luck Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" xintegrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@300..700&display=swap" rel="stylesheet">
    <style>
        /* Base styles (Dark Mode) */
        body {
            font-family: 'Inter', sans-serif; /* Changed to Inter as per instructions */
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background: radial-gradient(circle at center, #003333 0%, #000000 100%); /* Darker teal to black */
            color: #ffffff;
            transition: background 0.5s ease, color 0.5s ease; /* Smooth transition for theme change */
        }

        /* Light Mode styles (with darker blue shades) */
        body.light-mode {
            background: radial-gradient(circle at center, #99C2CC 0%, #547A9A 100%); /* Darker light blue to darker blue-grey */
            color: #333333; /* Darker text */
        }

        body.light-mode header {
            background: linear-gradient(to right, #B0E0E6, #4682B4, #B0E0E6); /* Lighter blue to steel blue gradient */
            border-bottom-color: #003366; /* Darker blue border */
            box-shadow: 0 5px 25px rgba(0, 51, 102, 0.3), /* Darker blue shadow */
                        0 0 30px rgba(0, 51, 102, 0.2); /* Darker blue glow */
        }

        body.light-mode .header-logo-container {
            color: #003366; /* Darker blue text */
            text-shadow: 0 0 5px rgba(0, 51, 102, 0.5), /* Darker blue shadow */
                         0 0 10px rgba(0, 51, 102, 0.3); /* Darker blue glow */
        }

        body.light-mode .header-logo-container:hover {
            text-shadow: 0 0 8px rgba(0, 51, 102, 0.7),
                         0 0 15px rgba(0, 51, 102, 0.5);
        }

        body.light-mode .trophy-button {
            border-color: #003366; /* Darker blue border */
            box-shadow: 0 0 10px rgba(0, 51, 102, 0.5); /* Darker blue shadow */
            color: #003366; /* Darker blue icon */
        }
        body.light-mode .trophy-button:hover {
            background-color: rgba(0, 51, 102, 0.1);
            box-shadow: 0 0 20px rgba(0, 51, 102, 0.7);
        }

        /* Light mode specific styles for the NEW theme toggle button in FOOTER */
        body.light-mode .theme-toggle-button {
            background-color: #B0C4DE; /* Light steel blue */
            border-color: #003366; /* Darker blue border */
            box-shadow: 0 0 15px rgba(0, 51, 102, 0.5); /* Darker blue glow */
            color: #003366; /* Darker blue icon */
        }
        body.light-mode .theme-toggle-button:hover {
            background-color: #ADD8E6; /* Lighter blue on hover */
            box-shadow: 0 0 25px rgba(0, 51, 102, 0.8);
        }

        /* Light mode specific styles for the NEW transaction/collection toggle */
        body.light-mode .toggle-container {
            border: 1px solid #003366; /* Darker blue border */
            background-color: #B0C4DE; /* Light steel blue background */
        }
        body.light-mode .toggle-label {
            color: #003366; /* Darker blue text */
        }
        body.light-mode .toggle-label.active {
            background-color: #4682B4; /* Steel blue active background */
            color: #FFFFFF; /* White text for active */
        }
        body.light-mode .toggle-switch + .toggle-label.left {
            color: #FFFFFF; /* Initial active state in light mode */
            background-color: #4682B4; /* Steel blue */
        }
        body.light-mode .toggle-switch:checked + .toggle-label.left {
            color: #003366; /* When checked, right is active, left is inactive */
            background-color: transparent;
        }
        body.light-mode .toggle-switch:checked + .toggle-label.left + .toggle-label.right {
            color: #FFFFFF; /* When checked, right is active */
            background-color: #4682B4; /* Steel blue */
        }
        body.light-mode .toggle-switch:not(:checked) + .toggle-label.left + .toggle-label.right {
            color: #003366; /* When not checked, right is inactive */
            background-color: transparent;
        }


        body.light-mode .user-profile-bubble {
            background-color: #6495ED; /* Cornflower blue */
            color: #000000;
            box-shadow: 0 0 10px rgba(0, 51, 102, 0.5); /* Darker blue shadow */
        }
        body.light-mode .user-profile-bubble:hover {
            background-color: #4682B4; /* Steel blue */
        }
        body.light-mode .account-id {
            color: #000000;
            text-shadow: 0 0 3px rgba(0, 0, 0, 0.3);
        }
        body.light-mode .dropdown-content {
            background-color: rgba(255, 255, 255, 0.9);
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
        }
        body.light-mode .dropdown-content a {
            color: #003366; /* Darker blue text */
            text-shadow: none;
        }
        body.light-mode .dropdown-content a:hover {
            background-color: #E0FFFF; /* Lighter blue hover */
        }

        body.light-mode .balance-section {
            background-color: rgba(194, 238, 255); /* Changed from gradient */
            border: 1px solid #003366; /* Steel blue border */
            box-shadow: 0 0 20px rgba(70, 130, 180, 0.7); /* Steel blue glow */
        }
        body.light-mode .balance-section img {
            border-color: #003366; /* Darker blue border */
            box-shadow: 0 0 10px rgba(0, 51, 102, 0.3); /* Darker blue shadow */
        }
        body.light-mode .amount-label {
            color: #003366; /* Darker blue */
        }
        body.light-mode .amount-value {
            color: #004080; /* Even darker blue for emphasis */
            text-shadow: 0 0 8px rgba(0, 64, 128, 0.5); /* Darker blue shadow */
        }
        body.light-mode footer {
            background-color: rgba(255, 255, 255, 0.6);
            color: #333333;
        }


        /* --- HEADER STYLES (Common for both modes, but values picked for dark) --- */
        header {
            background: linear-gradient(to right, #0a0a0a, #004d4d, #0a0a0a);
            padding: 10px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #ffffff;
            height: 4rem;
            border-bottom: 0.5px solid #00ffff;
            box-shadow: 0 5px 25px rgba(0, 255, 255, 0.5),
                        0 0 30px rgba(0, 255, 255, 0.3);
            z-index: 1000;
            position: sticky;
            top: 0;
            transition: all 0.4s ease-in-out;
        }

        .header-logo-container {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            width: auto;
            position: relative;
            height: auto;
            font-size: 2rem;
            color: #00ffff;
            text-shadow: 0 0 10px #00ffff,
                         0 0 20px rgba(0, 255, 255, 0.7);
            font-weight: bold;
            letter-spacing: 2px;
            transition: text-shadow 0.3s ease-in-out, color 0.3s ease-in-out;
        }

        .header-logo-container:hover {
            text-shadow: 0 0 15px #00ffff,
                         0 0 30px rgba(0, 255, 255, 0.9);
        }

        nav {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        /* --- LEADERBOARD BUTTON STYLES --- */
        .trophy-button { /* Renamed from trophy-button to reflect its new purpose, but kept class name for continuity */
            background-color: transparent;
            border: 2px solid #00e6e6;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            box-shadow: 0 0 10px rgba(0, 230, 230, 0.7);
            transition: all 0.3s ease;
            color: #00ffff;
            font-size: 1.2em;
            padding: 0;
        }

        .trophy-button:hover {
            background-color: rgba(0, 230, 230, 0.2);
            box-shadow: 0 0 20px rgba(0, 230, 230, 1);
        }

        /* Light mode specific styles for the Leaderboard button */
        body.light-mode .trophy-button {
            border-color: #003366; /* Darker blue border */
            box-shadow: 0 0 10px rgba(0, 51, 102, 0.5); /* Darker blue shadow */
            color: #003366; /* Darker blue icon */
        }
        body.light-mode .trophy-button:hover {
            background-color: rgba(0, 51, 102, 0.1);
            box-shadow: 0 0 20px rgba(0, 51, 102, 0.7);
        }


        /* --- NEW THEME TOGGLE BUTTON DESIGN (in FOOTER) --- */
        .theme-toggle-button {
            background-color: #004d4d; /* Dark teal background */
            border: 2px solid #00ffff; /* Bright teal border */
            border-radius: 50%; /* Circular */
            width: 45px; /* Slightly larger */
            height: 45px;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            box-shadow: 0 0 15px rgba(0, 255, 255, 0.7); /* Stronger glow */
            transition: all 0.3s ease;
            color: #ffffff; /* White icon in dark mode */
            font-size: 1.4em; /* Larger icon */
            padding: 0;
        }

        .theme-toggle-button:hover {
            background-color: #006666; /* Darker teal on hover */
            box-shadow: 0 0 25px rgba(0, 255, 255, 1); /* Intense glow on hover */
        }


        /* --- MAIN CONTENT STYLES (no change, but kept for context) --- */
        main {
            flex: 1;
            padding: 20px 20px 10px; /* Reduced bottom padding from 20px to 10px */
            display: flex;
            flex-direction: column; /* Stack elements vertically */
            gap: 20px;
            overflow: hidden; /* Prevent scrolling of main content */
            max-height: 100vh; /* Limit height to viewport */
            margin: 0;
            padding-left: 30px;
            max-width: none;
        }

        /* NEW: Container for balance and tasks sections to display them side-by-side */
        .dashboard-top-row {
            display: flex;
            flex-direction: row;
            justify-content: flex-start; /* Align to start */
            align-items: flex-start; /* Align items to the top */
            gap: 20px; /* Space between left-column and tasks-section */
            width: 100%; /* Take full width of main */
            margin-top: 20px; /* Maintain space from header */
        }

        /* NEW: Left Column for Balance and Transaction/Collection */
        .left-column {
            display: flex;
            flex-direction: column;
            flex: 1; /* This column takes 1 unit of available space */
            gap: 20px; /* Space between balance-section and transaction-collection-section */
            min-width: 0; /* Ensures flex items respect overflow */
        }
        
        .transaction-collection-section {
            display: flex;
            flex-direction: column;
            gap: 15px;
            width: 100%;
            min-width: 0; /* Prevents flex items from overflowing */
            padding: 0 15px; /* Add horizontal padding */
        }
        
        .transaction-content,
        .collection-content {
            width: 100%;
            display: flex;
            flex-direction: column;
            min-width: 0; /* Prevents flex items from overflowing */
        }

        /* --- balance-section with glowing border --- */
        .balance-section {
            display: flex;
            flex-direction: row; /* Changed to row to place buttons on the right */
            justify-content: space-between; /* Distribute space between items */
            align-items: center; /* Vertically align items in the center */
            gap: 15px;
            padding: 30px;
            background-color: rgba(0, 0, 0, 0.7); /* Solid color, no gradient */
            border-radius: 15px;
            border: 1px solid #00ffff; /* Cyan border */
            height: 130px; /* Fixed height to prevent adjustment */
            overflow: visible;
            flex-shrink: 0;
            transition: background 0.5s ease, box-shadow 0.5s ease, border-color 0.5s ease;
        }

        /* Adjusted styling for balance-buttons-container to include buttons */
        .balance-buttons-container {
            display: flex;
            flex-direction: row; /* Keep as row */
            align-items: center;
            gap: 20px; /* Space between buttons */
            flex-shrink: 0;
        }


        .balance-section img {
            max-width: 100px;
            height: auto;
            border-radius: 50%;
            border: 3px solid teal;
            box-shadow: 0 0 10px rgba(0, 255, 255, 0.5);
            flex-shrink: 0;
            margin: 0;
            transition: border-color 0.5s ease, box-shadow 0.5s ease;
        }

        /* Styles for the new account amount display */
        .account-amount-display {
            text-align: left;
            padding: 0;
            width: auto; /* Adjust width as needed */
            margin-top: -15px; /* Keep previous adjustment */
            flex-shrink: 0; /* Prevent shrinking if space is tight */
            margin-left: 20px; /* Add some left margin to balance content */
        }

        .amount-label {
            font-size: 1.5em;
            color: #b0e0e6;
            margin-bottom: 2px;
            font-weight: normal;
            line-height: 1.2;
            transition: color 0.5s ease;
        }

        .amount-value {
            font-size: 4em;
            color: #ffffff;
            text-shadow: 0 0 15px rgba(0, 255, 255, 0.9);
            font-weight: bold;
            margin: 0;
            line-height: 1.2;
            transition: color 0.5s ease, text-shadow 0.5s ease;
        }

        /* NEW: Styles for the tasks-section */
        .tasks-section {
            flex: 0.5; /* Allow it to grow and shrink, but with lesser width */
            padding: 30px;
            background-color: rgba(0, 0, 0, 0.7); /* Solid color, no gradient */
            border-radius: 15px;
            border: 1px solid #00ffff; /* Cyan border */
            height: 130px; /* Example height, adjust as needed */
            overflow: hidden; /* Add scroll if content overflows */
            flex-shrink: 1; /* Allows shrinking */
            transition: background 0.5s ease, box-shadow 0.5s ease, border-color 0.5s ease;
            color: #ffffff;
            display: flex; /* Use flexbox */
            flex-direction: column; /* Stack children vertically */
            align-items: center; /* Center horizontally */
        }

        .tasks-section h2 {
            text-align: center;
            color: #ffffff;
            text-shadow: 0 0 10px #00ffff;
            margin-top: -10px;
            margin-bottom: 0;
            font-size: 1.8em;
            transition: color 0.5s ease, text-shadow 0.5s ease;
            padding-bottom: 5px;
        }

        /* Style for individual task items */
        .task-item {
            display: flex;
            justify-content: space-between; /* Space out task text and button */
            align-items: center;
            width: 100%; /* Take full width of parent */
            margin-bottom: 10px; /* Space between tasks */
            font-size: 1em;
            color: #ffffff;
            transition: color 0.5s ease;
        }

        .task-item p {
            margin: 0; /* Remove default paragraph margin */
            flex-grow: 1; /* Allow text to take available space */
        }

        /* Style for the new Claim buttons */
        .claim-button {
            background-color: #008080; /* Dark teal */
            color: #ffffff; /* White text */
            border: 1px solid #00ffff; /* Cyan border */
            border-radius: 5px; /* Slightly rounded corners */
            padding: 5px 10px;
            font-size: 0.8em; /* Smaller font */
            cursor: pointer;
            transition: background-color 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
            box-shadow: 0 0 8px rgba(0, 255, 255, 0.5); /* Cyan glow */
            white-space: nowrap; /* Prevent text wrapping */
        }

        .claim-button:hover:not(:disabled) {
            background-color: #00cccc; /* Lighter teal on hover */
            box-shadow: 0 0 15px rgba(0, 255, 255, 0.8); /* More intense glow */
        }

        .claim-button:disabled {
            background-color: #333333; /* Darker grey when disabled */
            color: #888888; /* Lighter grey text when disabled */
            border-color: #555555;
            cursor: not-allowed;
            box-shadow: none; /* No glow when disabled */
            opacity: 0.7;
        }


        /* Light mode for tasks-section */
        body.light-mode .tasks-section {
            background-color: rgb(194, 238, 255); /* Solid color, no gradient */
            border: 1px solid #003366; /* Steel blue border */
            box-shadow: 0 0 20px rgba(70, 130, 180, 0.7); /* Steel blue glow */
            color: #333333;
        }

        body.light-mode .tasks-section h2 {
            color: #004080;
            text-shadow: 0 0 8px rgba(0, 64, 128, 0.5);
        }

        body.light-mode .task-item p {
            color:  #003366;
        }

        body.light-mode .claim-button {
            background-color: #6495ED; /* Cornflower blue */
            color: #ffffff;
            border: 1px solid #003366;
            box-shadow: 0 0 8px rgba(0, 51, 102, 0.5);
        }

        body.light-mode .claim-button:hover:not(:disabled) {
            background-color: #4682B4; /* Steel blue */
            box-shadow: 0 0 15px rgba(0, 51, 102, 0.8);
        }

        body.light-mode .claim-button:disabled {
            background-color: #CCCCCC; /* Lighter grey when disabled */
            color: #666666;
            border-color: #999999;
        }


        /* NEW: Styles for transaction-collection-section */
        .transaction-collection-section {
            display: flex;
            flex-direction: column;
            justify-content: flex-start; /* Align content to the top */
            align-items: center; /* Center content horizontally */
            gap: 20px;
            width: 154%; /* Make it responsive */
            /* Removed max-width to allow expansion */
            padding: 20px;
            background-color: rgba(0, 0, 0, 0.7); /* Slightly darker teal */
            border-radius: 15px;
            border: 1px solid #00ffff;
            transition: background 0.5s ease, box-shadow 0.5s ease, border-color 0.5s ease;
            color: #ffffff;
            min-height: 470px; /* Example height */
        }

        /* Light mode for transaction-collection-section */
        body.light-mode .transaction-collection-section {
            background-color: rgba(194, 238, 255); /* Match balance section */
            border: 1px solid #003366; /* Steel blue border */
            color: #004080; /* Dark blue text to match balance section */
            box-shadow: 0 0 20px rgba(0, 51, 102, 0.1); /* Subtle shadow */
        }

        .transaction-collection-section h3 {
            text-align: left;
            width: 100%;
            color: #ffffff;
            text-shadow: 0 0 10px #00ffff;
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 1.5em;
            transition: color 0.5s ease, text-shadow 0.5s ease;
            padding: 0 15px; /* Add padding to align with content */
        }

        body.light-mode .transaction-collection-section h3 {
            color: #003366; /* Dark blue to match balance section */
            text-shadow: 0 0 8px rgba(0, 64, 128, 0.3);
            font-weight: 600;
        }
        
        /* Transaction header in light mode */
        body.light-mode .transaction-header {
            padding: 8px 0; /* Remove horizontal padding */
            margin: 0 0 15px 0;
            background: transparent; /* Remove background color */
        }
        
        body.light-mode .section-heading {
            color: #001a33 !important; /* Darker blue for better contrast */
            text-shadow: none !important;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        
        body.light-mode .tx-count {
            background: rgba(0, 102, 204, 0.1) !important; /* Light blue background */
            color: #0066cc !important; /* Blue text */
            border: 1px solid rgba(0, 102, 204, 0.2);
        }


        /* --- FOOTER STYLES (Modified for button placement) --- */
        footer {
            background-color: rgba(0, 0, 0, 0.5);
            padding: 10px 30px; /* Added horizontal padding */
            display: flex; /* Use flexbox */
            justify-content: center; /* Center content horizontally */
            align-items: center; /* Vertically centers items */
            color: #ffffff;
            transition: background-color 0.5s ease, color 0.5s ease;
            text-align: center; /* Ensure text is centered */
        }

        /* --- HEADER STYLES (continued for user profile bubble and dropdown) --- */
.user-profile-bubble {
    position: relative;
    display: inline-block;
    background-color: #00e6e6;
    color: black;
    padding: 8px 15px;
    border-radius: 20px;
    cursor: pointer;
    font-weight: bold;
    box-shadow: 0 0 10px rgba(0, 230, 230, 0.7);
    transition: background-color 0.3s ease, box-shadow 0.3s ease;
}

.user-profile-bubble:hover {
    background-color: #00cccc;
}

/* Apply glow to the Account ID text inside the bubble */
.account-id {
    color: black;
    text-shadow: 0 0 5px rgba(0, 0, 0, 0.5);
    transition: color 0.3s ease, text-shadow 0.3s ease;
}

.dropdown-content {
    display: none; /* Hidden by default */
    position: absolute;
    background-color: rgba(0, 0, 0, 0.8);
    min-width: 160px;
    box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.7);
    z-index: 1;
    border-radius: 8px;
    overflow: hidden;
    right: 0;
    top: 100%;
    margin-top: 10px;
    transition: background-color 0.5s ease, box-shadow 0.5s ease;
}

/* Show the dropdown menu when 'show' class is present */
.dropdown-content.show {
    display: block;
}

/* Design for the logout button within the dropdown */
.dropdown-content a {
    color: #00ffff; /* Cyan text for dark mode */
    padding: 12px 16px;
    text-decoration: none;
    display: block;
    text-align: center;
    background-color: transparent; /* Ensure transparent background by default */
    text-shadow: 0 0 8px #00ffff, 0 0 15px rgba(0, 255, 255, 0.7); /* Cyan glow for dark mode */
    transition: background-color 0.3s ease, text-shadow 0.3s ease, color 0.3s ease;
}

.dropdown-content a:hover {
    background-color: #008080; /* Dark teal background on hover */
    text-shadow: 0 0 10px #00ffff, 0 0 20px rgba(0, 255, 255, 0.9); /* More intense cyan glow on hover */
    color: #ffffff; /* White text on hover */
}

/* Light mode specific styles for the logout button */
body.light-mode .dropdown-content a {
    color: #003366; /* Darker blue text */
    text-shadow: none; /* No text shadow in light mode by default */
}

body.light-mode .dropdown-content a:hover {
    background-color: #E0FFFF; /* Lighter blue hover */
    color: #003366; /* Darker blue text on hover */
    text-shadow: none; /* No text shadow on hover in light mode */
}


/* --- NEW TOGGLE SWITCH STYLES (Transactions/Collection) --- */
.toggle-container {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 300px; /* Adjust width as needed */
    height: 50px; /* Height of the toggle */
    border: 1px solid #00ffff; /* Cyan border */
    border-radius: 25px; /* Pill shape */
    overflow: hidden;
    position: relative;
    box-shadow: 0 0 15px rgba(0, 255, 255, 0.5);
    background-color: rgba(0, 0, 0, 0.7); /* Dark background */
    transition: all 0.3s ease;
    flex-shrink: 0; /* Prevent shrinking */
    margin-bottom: 20px; /* Add space below the toggle */
}

.toggle-switch {
    display: none; /* Hide the actual checkbox */
}

.toggle-label {
    flex: 1;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100%;
    cursor: pointer;
    font-size: 1.1em;
    font-weight: bold;
    color: #b0e0e6; /* Light cyan text for inactive */
    transition: background-color 0.3s ease, color 0.3s ease, text-shadow 0.3s ease;
    z-index: 1; /* Ensure labels are above the active background */
}

/* Initial active state for "Transactions" (left) */
.toggle-switch + .toggle-label.left {
    background-color: #00ffff; /* Cyan background for active */
    color: #0a0a0a; /* Dark text for active */
    text-shadow: 0 0 5px rgba(0, 0, 0, 0.5);
}

/* When checkbox is checked, "Collection" (right) becomes active */
.toggle-switch:checked + .toggle-label.left {
    background-color: transparent; /* Left becomes inactive */
    color: #b0e0e6; /* Light cyan text */
    text-shadow: none;
}

.toggle-switch:checked + .toggle-label.left + .toggle-label.right {
    background-color: #00ffff; /* Right becomes active */
    color: #0a0a0a; /* Dark text */
    text-shadow: 0 0 5px rgba(0, 0, 0, 0.5);
}

/* When checkbox is NOT checked, "Collection" (right) is inactive */
.toggle-switch:not(:checked) + .toggle-label.left + .toggle-label.right {
    background-color: transparent; /* Right is inactive */
    color: #b0e0e6; /* Light cyan text */
    text-shadow: none;
}

/* Styles for transaction and collection content */
.transaction-content, .collection-content {
    width: 100%;
    padding: 10px;
    box-sizing: border-box; /* Include padding in element's total width and height */
    text-align: center;
    color: #e0ffff; /* Light cyan text */
}

.collection-content {
    display: none; /* Hidden by default */
}

/* Light mode specific content styles */
body.light-mode .transaction-content,
body.light-mode .collection-content {
    color: #333333; /* Dark text in light mode */
}

/* --- MODAL STYLES --- */
.modal {
    display: none; /* Hidden by default */
    position: fixed; /* Stay in place */
    z-index: 2000; /* Sit on top, higher than header */
    left: 0;
    top: 0;
    width: 100%; /* Full width */
    height: 100%; /* Full height */
    overflow: auto; /* Enable scroll if needed */
    background-color: rgba(0, 0, 0, 0.7); /* Black w/ opacity */
    justify-content: center; /* Center horizontally */
    align-items: center; /* Center vertically */
    backdrop-filter: blur(5px); /* Frosted glass effect */
    transition: all 0.3s ease-in-out;
}

.modal.show {
    display: flex; /* Use flex to center content */
}

.modal-content {
    background: linear-gradient(to bottom right, #004d4d, #008080, #004d4d); /* Teal gradient */
    margin: auto; /* Centered */
    padding: 30px;
    border: 3px solid #00ffff; /* Cyan border */
    border-radius: 15px;
    width: 80%; /* Could be more specific like max-width */
    max-width: 700px; /* Max width of the modal content */
    box-shadow: 0 0 30px rgba(0, 255, 255, 0.8), 0 0 15px rgba(0, 255, 255, 0.5) inset; /* Outer glow and inner glow */
    position: relative;
    animation: fadeInScale 0.3s ease-out forwards; /* Animation for appearance */
    color: #ffffff;
    display: flex;
    flex-direction: column;
    gap: 20px;
}

/* Light mode specific modal styles */
body.light-mode .modal-content {
    background: linear-gradient(to bottom right, #B0E0E6, #87CEEB, #B0E0E6); /* Lighter blue gradient */
    border: 3px solid #4682B4; /* Steel blue border */
    box-shadow: 0 0 30px rgba(70, 130, 180, 0.8), 0 0 15px rgba(70, 130, 180, 0.5) inset; /* Steel blue glow */
    color: #333333; /* Darker text */
}

@keyframes fadeInScale {
    from {
        opacity: 0;
        transform: scale(0.9);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

.close-button {
    color: #00ffff; /* Cyan color */
    position: absolute;
    top: 15px;
    right: 25px;
    font-size: 35px;
    font-weight: bold;
    cursor: pointer;
    transition: color 0.3s ease, text-shadow 0.3s ease;
    text-shadow: 0 0 10px #00ffff; /* Glow effect */
}

.close-button:hover,
.close-button:focus {
    color: #ff0000; /* Red on hover */
    text-decoration: none;
    cursor: pointer;
    text-shadow: 0 0 15px #ff0000; /* More intense red glow */
}

/* Light mode specific close button styles */
body.light-mode .close-button {
    color: #003366; /* Dark blue color */
    text-shadow: none;
}
body.light-mode .close-button:hover,
body.light-mode .close-button:focus {
    color: #dc3545; /* Bootstrap red for danger */
    text-shadow: none;
}

.modal-content h2 {
    text-align: center;
    color: #ffffff; /* Cyan title */
    text-shadow: 0 0 15px #00ffff, 0 0 25px rgba(0, 255, 255, 0.7); /* Stronger glow */
    margin-top: 0;
    font-size: 2.5em;
    transition: color 0.5s ease, text-shadow 0.5s ease;
}

/* Light mode specific modal title */
body.light-mode .modal-content h2 {
    color: #004080; /* Darker blue */
    text-shadow: 0 0 8px rgba(0, 64, 128, 0.5); /* Darker blue shadow */
}

#leaderboardContent {
    background-color: rgba(0, 0, 0, 0.4); /* Slightly transparent background */
    padding: 20px;
    border-radius: 8px;
    min-height: 150px; /* Placeholder height */
    overflow-y: auto; /* Enable scrolling if content exceeds height */
    color: #e0ffff; /* Lighter cyan text */
    border: 1px solid #00cccc; /* Darker cyan border */
    box-shadow: inset 0 0 10px rgba(0, 255, 255, 0.3); /* Inner glow */
    transition: all 0.5s ease;
}

/* Light mode specific leaderboard content area */
body.light-mode #leaderboardContent {
    background-color: rgba(255, 255, 255, 0.7); /* Slightly transparent white background */
    color: #333333; /* Dark text */
    border: 1px solid #6A8CAE; /* Darker blue-grey border */
    box-shadow: inset 0 0 10px rgba(70, 130, 180, 0.3); /* Inner glow */
}

/* NEW: Container for buttons and toggle in one row */
.transaction-controls-row {
    display: flex;
    justify-content: center; /* Center the entire row */
    align-items: center;
    gap: 20px; /* Space between items in the row */
    width: 100%; /* Take full width to distribute items */
    margin-top: 20px; /* Add some space from the balance section   */
}


/* Styles for individual transaction buttons (now circular with icons) */
.transaction-button {
    background-color: #008080; /* Teal background */
    color: #ffffff; /* White icon color */
    border: 2px solid #00ffff; /* Cyan border */
    border-radius: 8px; /* Make them square with slightly rounded corners */
    width: 70px; /* Fixed width for square shape */
    height: 70px; /* Fixed height for square shape */
    font-size: 2.2em; /* Icon size */
    cursor: pointer;
    text-align: center;
    box-shadow: 0 0 15px rgba(0, 255, 255, 0.5); /* Cyan glow */
    transition: all 0.3s ease;
    display: flex; /* Use flexbox to center content */
    justify-content: center;
    align-items: center;
    flex-shrink: 0; /* Prevent shrinking if space is tight */
    position: relative; /* For tooltip positioning */
    overflow: hidden; /* Ensure text stays within bounds and allows fade-in */
}

.transaction-button:hover {
    background-color: #00cccc; /* Lighter teal on hover */
    box-shadow: 0 0 25px rgba(0, 255, 255, 0.8), 0 0 10px rgba(0, 255, 255, 0.5) inset; /* More intense glow with inner shadow */
    transform: translateY(-2px); /* Slight lift effect */
}

.transaction-button:active {
    background-color: #006666; /* Even darker teal on click */
    box-shadow: 0 0 10px rgba(0, 255, 255, 0.3); /* Reduced glow on click */
    transform: translateY(0); /* Return to original position */
}

/* Light mode specific transaction button styles */
body.light-mode .transaction-button {
    background-color: #4682B4; /* Steel blue */
    color: #ffffff; /* White icon color */
    border: 2px solid #003366; /* Darker blue border */
    box-shadow: 0 0 15px rgba(0, 51, 102, 0.5); /* Darker blue glow */
}

body.light-mode .transaction-button:hover {
    background-color: #5F9EA0; /* Cadet blue */
    box-shadow: 0 0 25px rgba(0, 51, 102, 0.8), 0 0 10px rgba(0, 51, 102, 0.5) inset;
}

body.light-mode .transaction-button:active {
    background-color: #366795;
    box-shadow: 0 0 10px rgba(0, 51, 102, 0.3);
}

/* Styles for the icon inside the button */
.transaction-button .fa-solid {
    transition: opacity 0.3s ease;
    position: absolute; /* Keep icon positioned for transition */
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
}

/* Custom Tooltip Text Styles (inside the button) */
.button-tooltip-text {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%); /* Center the text */
    background-color: transparent; /* No background within the button */
    color: #fff;
    padding: 0; /* No padding as it's inside */
    font-size: 0.5em; /* Even smaller font size for better readability and fit */
    white-space: nowrap; /* Prevent text from wrapping */
    opacity: 0; /* Initially hidden */
    visibility: hidden;
    transition: opacity 0.3s ease, visibility 0.3s ease; /* Faded hover effect */
    z-index: 2; /* Ensure text is above the icon when visible */
    pointer-events: none; /* Allows clicks to pass through the tooltip */
}

/* Hover effect: Icon fades, text appears */
.transaction-button:hover .fa-solid {
    opacity: 0.1; /* Icon fades out */
}

.transaction-button:hover .button-tooltip-text {
    opacity: 1; /* Text fades in */
    visibility: visible;
}

/* Light mode specific tooltip styles */
body.light-mode .button-tooltip-text {
    color: #333; /* Dark text in light mode */
}

/* Additional styles for modal content (deposit and send) */
.modal-inner-content { /* Generic class for content inside modals */
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 15px;
    padding: 20px;
    width: 100%;
}

.modal-inner-content p {
    font-size: 1.2em;
    color: #e0ffff; /* Light cyan text */
    margin: 0;
    text-align: center;
}

body.light-mode .modal-inner-content p {
    color: #333333; /* Dark text in light mode */
}

.user-id-display, .modal-input-field {
    background-color: rgba(0, 0, 0, 0.3);
    border: 1px solid #00cccc;
    border-radius: 8px;
    padding: 10px 15px;
    font-family: 'Fira Code', monospace;
    font-size: 1.1em;
    color: #00ffff;
    word-break: break-all; /* Break long IDs */
    text-align: center;
    width: 90%;
    max-width: 400px;
    box-shadow: inset 0 0 8px rgba(0, 255, 255, 0.2);
    display: flex;
    justify-content: center;
    align-items: center;
    box-sizing: border-box; /* Include padding and border in the element's total width and height */
}

.modal-input-field {
    background-color: rgba(0, 0, 0, 0.5); /* Slightly darker for input */
    color: #00ffff;
    border: 1px solid #00ffff;
    padding: 12px 18px;
    font-size: 1.1em;
    text-align: left; /* Align text to left for input */
}

body.light-mode .user-id-display, body.light-mode .modal-input-field {
    background-color: rgba(255, 255, 255, 0.7);
    border: 1px solid #6A8CAE;
    color: #003366;
    box-shadow: inset 0 0 8px rgba(70, 130, 180, 0.2);
}

body.light-mode .modal-input-field {
    background-color: rgba(255, 255, 255, 0.9);
    border: 1px solid #4682B4;
}

.copy-button, .send-funds-button {
    background-color: #008080; /* Teal background */
    color: #ffffff; /* White icon color */
    border: 2px solid #00ffff; /* Cyan border */
    border-radius: 8px;
    padding: 10px 20px;
    font-size: 1.1em;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 0 15px rgba(0, 255, 255, 0.5);
    transition: all 0.3s ease;
}

.copy-button:hover, .send-funds-button:hover {
    background-color: #00cccc;
    box-shadow: 0 0 25px rgba(0, 255, 255, 0.8);
    transform: translateY(-2px);
}

.copy-button:active, .send-funds-button:active {
    background-color: #006666;
    box-shadow: 0 0 10px rgba(0, 255, 255, 0.3);
    transform: translateY(0);
}

body.light-mode .copy-button, body.light-mode .send-funds-button {
    background-color: #4682B4;
    color: #ffffff;
    border: 2px solid #003366;
    box-shadow: 0 0 15px rgba(0, 51, 102, 0.5);
}

body.light-mode .copy-button:hover, body.light-mode .send-funds-button:hover {
    background-color: #5F9EA0;
    box-shadow: 0 0 25px rgba(0, 51, 102, 0.8);
}

body.light-mode .copy-button:active, body.light-mode .send-funds-button:active {
    background-color: #366795;
    box-shadow: 0 0 10px rgba(0, 51, 102, 0.3);
}

/* Message box for copy confirmation */
.message-box {
    display: none; /* Hidden by default */
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background-color: #008080;
    color: #ffffff;
    padding: 15px 30px;
    border-radius: 10px;
    box-shadow: 0 0 20px rgba(0, 255, 255, 0.7);
    z-index: 3000; /* Higher than modals */
    font-size: 1.1em;
    text-align: center;
    opacity: 0;
    transition: opacity 0.3s ease-in-out;
}

.message-box.show {
    display: block;
    opacity: 1;
}

body.light-mode .message-box {
    background-color: #4682B4;
    color: #ffffff;
    box-shadow: 0 0 20px rgba(70, 130, 180, 0.7);
}

/* Styles for the NFT Grid (inside collection-content) */
.nft-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 1.5rem;
    padding: 1.5rem 0;
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
}

/* Styles for individual NFT items */
.nft-item {
    background: rgba(0, 0, 0, 0.3);
    border-radius: 12px;
    overflow: hidden;
    text-align: center;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    border: 1px solid rgba(0, 255, 255, 0.2);
    padding: 1rem;
}

.nft-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0, 255, 255, 0.2);
}

.nft-placeholder {
    width: 100%;
    height: 180px;
    background: linear-gradient(135deg, rgba(0, 255, 255, 0.1) 0%, rgba(0, 200, 200, 0.2) 100%);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: rgba(255, 255, 255, 0.5);
    margin-bottom: 1rem;
    border: 2px dashed rgba(0, 255, 255, 0.3);
}

.nft-item p {
    margin: 0.5rem 0 0;
    color: #00ffff;
    font-size: 0.9rem;
}

/* Light mode styles */
body.light-mode .nft-item {
    background: rgba(255, 255, 255, 0.2);
    border-color: rgba(0, 100, 150, 0.3);
}

body.light-mode .nft-placeholder {
    background: linear-gradient(135deg, rgba(0, 150, 200, 0.1) 0%, rgba(0, 100, 150, 0.2) 100%);
    border-color: rgba(0, 100, 150, 0.3);
    color: rgba(0, 100, 150, 0.5);
}

body.light-mode .nft-item p {
    color: #004d74;
}

/* Section headings for Transaction History and Collections */
.section-heading {
    font-size: 1.1em;
    margin: -20px 0 5px 0; /* Increased negative top margin to move it higher */
    padding: 0;
    color: #00ffff; /* Cyan color to match the theme */
    text-align: left;
    width: 100%;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 1px;
}

body.light-mode .section-heading {
    color: #003366; /* Dark blue for light mode */
}

/* Transaction List Styles */
.transactions-list {
    margin-top: 15px;
    max-height: 500px;
    overflow-y: auto;
    padding-right: 10px;
    scrollbar-width: thin;
    scrollbar-color: #00e6e6 #003333;
}

/* Light mode scrollbar */
body.light-mode .transactions-list {
    scrollbar-color: #0077b3 #e6f7ff;
}

/* Custom scrollbar for Webkit browsers */
.transactions-list::-webkit-scrollbar {
    width: 6px;
}

.transactions-list::-webkit-scrollbar-track {
    background: #003333;
    border-radius: 3px;
}

body.light-mode .transactions-list::-webkit-scrollbar-track {
    background: #e6f7ff;
}

.transactions-list::-webkit-scrollbar-thumb {
    background-color: #00e6e6;
    border-radius: 3px;
}

body.light-mode .transactions-list::-webkit-scrollbar-thumb {
    background-color: #0077b3;
}

.transactions-list::-webkit-scrollbar-thumb:hover {
    background-color: #00cccc;
}

body.light-mode .transactions-list::-webkit-scrollbar-thumb:hover {
    background-color: #005580;
}

/* Transaction Item Styles */
.transaction-item {
    background: rgba(0, 0, 0, 0.3);
    border-radius: 12px;
    padding: 16px 20px;
    display: flex;
    align-items: center;
    transition: all 0.3s ease;
    margin-bottom: 12px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(0, 230, 230, 0.2);
    position: relative;
    overflow: hidden;
}

/* Light mode transaction item */
body.light-mode .transaction-item {
    background: rgba(255, 255, 255, 0.95);
    border: 1px solid rgba(0, 102, 153, 0.2);
    color: #003366;
    box-shadow: 0 2px 10px rgba(0, 51, 102, 0.08);
    transition: all 0.3s ease;
}

/* Ensure transaction item text is visible in light mode */
body.light-mode .transaction-item,
body.light-mode .transaction-item .tx-details,
body.light-mode .transaction-item .tx-address,
body.light-mode .transaction-item .tx-date,
body.light-mode .transaction-item .tx-amount,
body.light-mode .transaction-item .tx-notes {
    color: #003366 !important;
}

/* Make sure icons in transaction items are visible */
body.light-mode .transaction-item i {
    color: #005580 !important;
}

/* Ensure transaction type/status is visible */
body.light-mode .transaction-item .tx-type,
body.light-mode .transaction-item .tx-status {
    color: #005580 !important;
    background-color: rgba(0, 102, 153, 0.1) !important;
    border: 1px solid rgba(0, 102, 153, 0.2) !important;
}

/* Light mode transaction header */
body.light-mode .transaction-header .section-heading {
    color: #003366 !important;
}

/* Light mode transaction count */
body.light-mode .tx-count {
    background: rgba(0, 102, 153, 0.1) !important;
    color: #003366 !important;
    border: 1px solid rgba(0, 102, 153, 0.2) !important;
}

body.light-mode .transaction-item:hover {
    background: #ffffff;
    border-color: rgba(0, 102, 153, 0.4);
    box-shadow: 0 4px 12px rgba(0, 51, 102, 0.12);
    transform: translateY(-1px);
}

/* Transaction item accent bar */
.transaction-item::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: linear-gradient(to bottom, #00e6e6, #008080);
}

body.light-mode .transaction-item::before {
    background: linear-gradient(to bottom, #0077b3, #005580);
}

/* Hover state */
.transaction-item:hover {
    background: rgba(0, 0, 0, 0.4);
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
    border-color: rgba(0, 230, 230, 0.4);
}

body.light-mode .transaction-item:hover {
    background: rgba(255, 255, 255, 0.9);
    border-color: rgba(0, 119, 179, 0.3);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

/* Transaction details */
.tx-details {
    flex: 1;
    margin-left: 15px;
}

.tx-address {
    font-weight: 500;
    margin-bottom: 4px;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 6px;
}

/* Transaction details in light mode */
body.light-mode .tx-address {
    color: #001a33; /* Darker blue for better contrast */
    font-weight: 600;
}

.tx-date {
    font-size: 0.85em;
    color: #aaa;
    display: flex;
    align-items: center;
    gap: 4px;
}

body.light-mode .tx-date {
    color: #334d66; /* Darker muted blue for better contrast */
    font-size: 0.8em;
}

.tx-amount {
    font-weight: 700;
    font-size: 1.1em;
    transition: all 0.2s ease;
}

/* Received amount */
.tx-amount.received {
    color: #2ecc71;
}

body.light-mode .tx-amount.received {
    color: #156538; /* Even darker green for better contrast */
    text-shadow: 0 1px 2px rgba(21, 101, 56, 0.1);
    font-weight: 700;
}

/* Sent amount */
.tx-amount.sent {
    color: #ff6b6b;
}

body.light-mode .tx-amount.sent {
    color: #a52714; /* Even darker red for better contrast */
    text-shadow: 0 1px 2px rgba(165, 39, 20, 0.1);
    font-weight: 700;
}

/* Conversion amount */
.tx-amount.conversion {
    color: #f39c12;
}

body.light-mode .tx-amount.conversion {
    color: #8e5c0a; /* Even darker orange for better contrast */
    text-shadow: 0 1px 2px rgba(142, 92, 10, 0.1);
    font-weight: 700;
}

/* Transaction status */
.tx-status {
    font-size: 0.7em;
    padding: 3px 10px;
    border-radius: 12px;
    margin-left: 8px;
    text-transform: uppercase;
    font-weight: 700;
    letter-spacing: 0.5px;
    border: 1px solid transparent;
}

/* Completed status */
.tx-status.completed {
    background: rgba(46, 204, 113, 0.2);
    color: #27ae60;
    border-color: rgba(39, 174, 96, 0.3);
}

body.light-mode .tx-status.completed {
    background: rgba(39, 174, 96, 0.15);
    color: #156538;
    border-color: rgba(21, 101, 56, 0.25);
    font-weight: 600;
}

/* Pending status */
.tx-status.pending {
    background: rgba(241, 196, 15, 0.2);
    color: #f39c12;
    border-color: rgba(241, 196, 15, 0.3);
}

body.light-mode .tx-status.pending {
    background: rgba(241, 196, 15, 0.15);
    color: #8e5c0a;
    border-color: rgba(185, 119, 14, 0.25);
    font-weight: 600;
}

/* Failed status */
.tx-status.failed {
    background: rgba(231, 76, 60, 0.2);
    color: #e74c3c;
    border-color: rgba(231, 76, 60, 0.3);
}

body.light-mode .tx-status.failed {
    background: rgba(231, 76, 60, 0.15);
    color: #a52714;
    border-color: rgba(165, 39, 20, 0.25);
    font-weight: 600;
}

/* No transactions state */
.no-transactions {
    text-align: center;
    padding: 40px 20px;
    color: #aaa;
    background: rgba(0, 0, 0, 0.05);
    border-radius: 12px;
    margin: 15px 0;
    border: 1px dashed rgba(0, 0, 0, 0.1);
}

body.light-mode .no-transactions {
    color: #4a6b8a;
    background: rgba(194, 238, 255, 0.3);
    border-color: rgba(0, 102, 153, 0.1);
}

.no-transactions i {
    font-size: 2.5em;
    margin-bottom: 15px;
    opacity: 0.8;
    color: #00a8e8;
}

body.light-mode .no-transactions i {
    color: #0066cc;
    opacity: 0.9;
}

.no-transactions h4 {
    margin: 10px 0 5px;
    font-size: 1.2em;
    font-weight: 600;
}

body.light-mode .no-transactions h4 {
    color: #003366;
}

.no-transactions p {
    margin: 0;
    font-size: 0.95em;
    opacity: 0.9;
}

/* Error state */
.error-message {
    text-align: center;
    padding: 30px 20px;
    background: rgba(255, 0, 0, 0.05);
    border-radius: 12px;
    margin: 15px 0;
    border: 1px dashed rgba(255, 0, 0, 0.1);
}

body.light-mode .error-message {
    background: rgba(231, 76, 60, 0.08);
    border-color: rgba(231, 76, 60, 0.1);
}

.error-message i {
    font-size: 2.5em;
    margin-bottom: 15px;
    color: #ff6b6b;
}

body.light-mode .error-message i {
    color: #c0392b;
}

.error-message h4 {
    margin: 10px 0 5px;
    color: #ff6b6b;
    font-size: 1.2em;
    font-weight: 600;
}

body.light-mode .error-message h4 {
    color: #c0392b;
}

.error-message p {
    margin: 0;
    font-size: 0.95em;
    opacity: 0.9;
}

body.light-mode .error-message p {
    color: #4a6b8a;
}

.transaction-item.sent {
    border-left-color: #ff6b6b;
}

.transaction-item.received {
    border-left-color: #6bff6b;
}

.tx-icon {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 16px;
    font-size: 16px;
    color: white;
    background: rgba(0, 0, 0, 0.2);
    border: 2px solid rgba(0, 230, 230, 0.3);
    transition: all 0.3s ease;
    position: relative;
    z-index: 1;
}

.transaction-item:hover .tx-icon {
    transform: scale(1.1);
    border-color: #00e6e6;
}

.tx-icon.sent {
    background: rgba(255, 99, 71, 0.15);
    color: #ff6b6b;
    border-color: rgba(255, 107, 107, 0.3);
}

.tx-icon.received {
    background: rgba(50, 205, 50, 0.15);
    color: #2ecc71;
    border-color: rgba(46, 204, 113, 0.3);
}

.tx-details {
    flex: 1;
    padding-right: 15px;
}

.tx-address {
    font-weight: 500;
    margin-bottom: 5px;
    color: #e0e0e0;
    font-size: 15px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.tx-address i {
    font-size: 14px;
    opacity: 0.8;
}

.tx-date {
    font-size: 12px;
    color: #999;
    display: flex;
    align-items: center;
    gap: 5px;
}

.tx-date i {
    font-size: 11px;
}

.tx-amount {
    font-weight: 600;
    font-family: 'Fira Code', monospace;
    font-size: 15px;
    padding: 6px 12px;
    border-radius: 20px;
    min-width: 90px;
    text-align: right;
    transition: all 0.3s ease;
    white-space: nowrap;
}

.tx-amount.sent {
    background: rgba(255, 107, 107, 0.1);
    color: #ff6b6b;
    border: 1px solid rgba(255, 107, 107, 0.2);
}

.tx-amount.received,
.tx-amount.conversion,
.transaction-item[data-type="conversion"] .tx-amount {
    background: rgba(46, 204, 113, 0.1);
    color: #2ecc71 !important;
    border: 1px solid rgba(46, 204, 113, 0.2);
}

.transaction-item:hover .tx-amount {
    background: rgba(0, 0, 0, 0.4);
    border-color: rgba(0, 230, 230, 0.3);
}

/* Status badges */
.tx-status {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-left: 8px;
}

.tx-status.completed {
    background: rgba(46, 204, 113, 0.15);
    color: #2ecc71;
}

.tx-status.pending {
    background: rgba(241, 196, 15, 0.15);
    color: #f1c40f;
}

.loading-spinner,
.no-transactions,
.error-message {
    text-align: center;
    padding: 30px 0;
    color: #aaa;
}

.loading-spinner i,
.no-transactions i,
.error-message i {
    font-size: 2em;
    margin-bottom: 10px;
    display: block;
}

/* Scrollbar styling */
.transactions-list::-webkit-scrollbar {
    width: 6px;
}

.transactions-list::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.1);
    border-radius: 3px;
}

.transactions-list::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 3px;
}

.transactions-list::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.3);
}

/* Light mode adjustments */
body.light-mode .transaction-item {
    background: rgba(255, 255, 255, 0.1);
}

body.light-mode .transaction-item:hover {
    background: rgba(255, 255, 255, 0.2);
}

body.light-mode .tx-date {
    color: #666;
}

    </style>
</head>
<body>
    <header>
        <div class="header-logo-container">
            LUCK WALLET
        </div>
        <nav>
            <button id="themeToggleButton" class="theme-toggle-button" aria-label="Toggle dark/light mode">
            <i class="fas fa-sun"></i> </button>
            <button class="trophy-button" aria-label="Leaderboard">
                <i class="fa-solid fa-list-ol"></i>
            </button>
            <div class="user-profile-bubble" id="userProfileBubble">
                <span class="account-id" id="userAccountId"><?php echo htmlspecialchars($currentUser['wallet_address']); ?></span>
                <div class="dropdown-content">
                    <a href="/Luck%20Wallet/auth/logout.php" class="logout-button">Logout</a>
                </div>
            </div>
        </nav>
    </header>
    <main>
        <div class="dashboard-top-row">
            <div class="left-column">
                <div class="balance-section">
                    <div class="account-amount-display">
                        <p class="amount-label">Current Balance:</p>
                        <p class="amount-value" id="currentAccountAmount">0.00 $LUCK</p>
                    </div>
                    <div class="balance-buttons-container">
                        <button class="transaction-button" id="sendButton" aria-label="Send">
                            <i class="fa-solid fa-paper-plane"></i>
                            <span class="button-tooltip-text">Send</span>
                        </button>
                        <button class="transaction-button" id="depositButton" aria-label="Deposit">
                            <i class="fa-solid fa-wallet"></i>
                            <span class="button-tooltip-text">Receive</span>
                        </button>
                        <button class="transaction-button" id="convertButton" aria-label="Convert">
                            <i class="fa-solid fa-right-left"></i>
                            <span class="button-tooltip-text">Convert</span>
                        </button>
                    </div>
                </div>
                <div class="transaction-collection-section" style="height: 500px; display: flex; flex-direction: column;">
                    <div class="toggle-container" style="flex-shrink: 0;">
                        <input type="checkbox" id="viewToggle" class="toggle-switch">
                        <label class="toggle-label left" for="viewToggle">Transactions</label>
                        <label class="toggle-label right" for="viewToggle">Collection</label>
                    </div>
                    
                    <div id="transactionContent" class="transaction-content" style="flex: 1; display: flex; flex-direction: column; padding: 0; margin: 10px 0 0 0; overflow: hidden;">
                        <h3 class="section-heading" style="margin: 0 0 15px 0; padding: 0 15px; font-size: 1.1em; color: #00e6e6; flex-shrink: 0;">TRANSACTION HISTORY</h3>
                        <div class="transactions-list" style="flex: 1; overflow-y: auto; overflow-x: hidden; padding: 0 10px 10px 15px;"></div>
                    </div>
                    <div id="collectionContent" class="collection-content" style="display: none; flex: 1; flex-direction: column; padding: 0; overflow: hidden; position: relative;">
                        <div class="transaction-header" style="display: flex; justify-content: space-between; align-items: center; margin: 10px 0 15px 0; padding: 0 20px; flex-shrink: 0;">
                            <h3 class="section-heading" style="margin: 0; font-size: 1.1em; color: #00e6e6; text-transform: uppercase; letter-spacing: 1px; font-weight: 600;">MY COLLECTION</h3>
                            <div class="tx-count" style="background: rgba(0, 230, 230, 0.2); padding: 4px 12px; border-radius: 15px; font-size: 0.9em; color: #00e6e6; font-weight: 500;">
                                <span id="nftCount">0</span> Items
                            </div>
                        </div>
                        <div id="nftCollection" class="nft-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px; padding: 0 15px 15px; width: 100%; box-sizing: border-box; overflow-y: auto; height: 100%; align-items: start;">
                            <!-- NFTs will be loaded here by JavaScript -->
                            <div class="text-center" style="grid-column: 1 / -1; padding: 30px 0; width: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 200px;">
                                <div class="spinner-border text-primary" role="status" style="color: #00e6e6; width: 2.5rem; height: 2.5rem; border-width: 0.25em; margin: 0 auto;">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="mt-3" style="color: #a0aec0; margin-top: 15px;">Loading your collection...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="tasks-section">
                <h2>EARN $LUCK</h2>
                <div class="task-item">
                    <p>Daily Login Bonus (+50 $LUCK)</p>
                    <button class="claim-button" id="dailyLoginButton">Claim</button>
                </div>
                <div class="task-item">
                    <p>Play games and convert your winnings to $LUCK</p>
                    <button class="claim-button" id="eCasinoButton">Go</button>
                </div>
                <div class="task-item" data-task-type="invite">
                    <p>Invite a friend!</p>
                    <button class="claim-button" id="inviteFriendBtn" data-task-type="invite">Go</button>
                </div>
            </div>
        </div>

    </main>

    <!-- Invite a Friend Modal -->
    <div id="inviteFriendModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.7); z-index: 1000; overflow-y: auto;">
        <div class="modal-content" style="background-color: #0a1a2a; margin: 15% auto; padding: 20px; border-radius: 8px; width: 90%; max-width: 500px; position: relative;">
            <span class="close-button" onclick="document.getElementById('inviteFriendModal').style.display='none';">&times;</span>
            <h2>Invite a Friend</h2>
            <div class="modal-inner-content">
                <p>Share your referral code and earn 500 $LUCK for each friend who joins!</p>
                
                <div style="margin: 20px 0; text-align: center;">
                    <label for="referralCode" style="display: block; margin-bottom: 8px; color: #00e6e6;">Your Referral Code</label>
                    <div style="display: flex; justify-content: center; gap: 10px; max-width: 400px; margin: 0 auto;">
                        <input type="text" id="referralCode" readonly class="modal-input-field" style="flex: 1; font-family: monospace; font-size: 1.1em; letter-spacing: 2px; text-align: center;" value="Loading...">
                        <button id="copyReferralBtn" class="modal-button" style="width: auto; padding: 0 15px;">
                            <i class="far fa-copy"></i> Copy
                        </button>
                    </div>
                </div>


                <div style="margin-top: 25px; padding: 15px; background: rgba(0, 230, 230, 0.1); border-radius: 8px; border-left: 3px solid #00e6e6;">
                    <p style="margin: 0; color: #00e6e6; font-size: 0.9em;">
                        <i class="fas fa-info-circle"></i> You'll receive 500 $LUCK when your friend joins using your code.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <p>&copy; 2025 LuckyWallet. All rights reserved.</p>
    </footer>

    <div id="leaderboardModal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <span class="close-button" data-modal-id="leaderboardModal">&times;</span>
            <h2>Top LUCK Holders</h2>
            <div id="leaderboardContent" style="margin-top: 20px;">
                <div class="leaderboard-loading" style="text-align: center; padding: 20px;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 24px; color: #00e6e6;"></i>
                    <p>Loading leaderboard...</p>
                </div>
                <div id="leaderboardError" style="display: none; text-align: center; color: #ff6b6b; padding: 20px;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 24px;"></i>
                    <p>Failed to load leaderboard. Please try again later.</p>
                    <button onclick="loadLeaderboard()" class="retry-button" style="margin-top: 10px; padding: 8px 16px; background: #00e6e6; border: none; border-radius: 4px; cursor: pointer; color: #000;">
                        <i class="fas fa-sync-alt"></i> Try Again
                    </button>
                </div>
                <div id="leaderboardList" style="display: none;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="border-bottom: 1px solid #333;">
                                <th style="text-align: left; padding: 10px; width: 60px;">Rank</th>
                                <th style="text-align: left; padding: 10px;">User</th>
                                <th style="text-align: right; padding: 10px;">Balance</th>
                            </tr>
                        </thead>
                        <tbody id="leaderboardBody">
                            <!-- Leaderboard rows will be inserted here by JavaScript -->
                        </tbody>
                    </table>
                </div>
                <div class="leaderboard-loading" style="text-align: center; padding: 20px;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 24px; color: #00e6e6;"></i>
                    <p>Loading leaderboard data...</p>
                </div>
                <div id="leaderboardList" style="display: none;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="border-bottom: 1px solid #00e6e6;">
                                <th style="text-align: left; padding: 10px; color: #00e6e6;">Rank</th>
                                <th style="text-align: left; padding: 10px; color: #00e6e6;">User</th>
                                <th style="text-align: right; padding: 10px; color: #00e6e6;">Balance</th>
                            </tr>
                        </thead>
                        <tbody id="leaderboardBody">
                            <!-- Leaderboard rows will be inserted here by JavaScript -->
                        </tbody>
                    </table>
                </div>
                <div id="leaderboardError" class="error-message" style="display: none; color: #ff6b6b; text-align: center; padding: 20px;">
                    Could not load leaderboard. Please try again later.
                </div>
            </div>
        </div>
    </div>
    <style>
        .leaderboard-row {
            border-bottom: 1px solid rgba(0, 230, 230, 0.2);
            transition: background-color 0.2s;
        }
        .leaderboard-row:hover {
            background-color: rgba(0, 230, 230, 0.1);
        }
        .leaderboard-row td {
            padding: 12px 10px;
        }
        .leaderboard-username {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .leaderboard-wallet {
            font-size: 0.8em;
            opacity: 0.7;
        }
        .leaderboard-balance {
            color: #00e6e6;
            font-weight: bold;
        }
        .rank-1 .rank-badge {
            background-color: #ffd700;
            color: #000;
        }
        .rank-2 .rank-badge {
            background-color: #c0c0c0;
            color: #000;
        }
        .rank-3 .rank-badge {
            background-color: #cd7f32;
            color: #fff;
        }
        .rank-badge {
            display: inline-block;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            text-align: center;
            line-height: 24px;
            font-weight: bold;
            margin-right: 8px;
        }
    </style>

    <div id="depositModal" class="modal">
        <div class="modal-content">
            <span class="close-button" data-modal-id="depositModal">&times;</span>
            <h2>Your Wallet Address</h2>
            <div class="modal-inner-content">
                <p>Share this address to receive funds:</p>
                <div class="user-id-display" id="modalUserIdDisplay">
                    </div>
                <button class="copy-button" id="copyUserIdButton">
                    <i class="fa-solid fa-copy"></i> Copy ID
                </button>
            </div>
        </div>
    </div>

    <div id="sendModal" class="modal">
        <div class="modal-content">
            <span class="close-button" data-modal-id="sendModal">&times;</span>
            <h2>Send $LUCK</h2>
            <div class="modal-inner-content">
                <p>Recipient User ID:</p>
                <input type="text" id="recipientIdInput" class="modal-input-field" placeholder="Enter valid wallet address">
                
                <p>Amount to Send:</p>
                <input type="number" id="amountToSendInput" class="modal-input-field" placeholder="Enter amount in $LUCK" min="0.01" step="0.01">
                
                <button class="send-funds-button" id="sendFundsButton">
                    <i class="fa-solid fa-paper-plane"></i> Send Funds
                </button>
            </div>
        </div>
    </div>

    <div id="messageBox" class="message-box">
        ID copied to clipboard!
    </div>

    <!-- Invite Friend Modal -->
    <div id="inviteFriendModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 500px;">
            <span class="close-button" data-modal-id="inviteFriendModal">&times;</span>
            <h2>Invite Friends & Earn</h2>
            <div style="padding: 20px;">
                <div style="background: rgba(0, 0, 0, 0.2); border-radius: 10px; padding: 20px; margin-bottom: 20px;">
                    <p style="margin-bottom: 15px; font-size: 16px; color: #fff;">Share your referral code and earn 500 $LUCK for each friend who signs up!</p>
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 8px; color: #aaa;">Your Referral Code:</label>
                        <div style="display: flex; gap: 10px;">
                            <input type="text" id="referralCode" readonly style="
                                flex: 1;
                                padding: 12px 15px;
                                background: rgba(255, 255, 255, 0.1);
                                border: 1px solid #00e6e6;
                                border-radius: 8px;
                                color: #fff;
                                font-size: 16px;
                                font-family: monospace;
                                letter-spacing: 1px;
                            ">
                            <button id="copyReferralBtn" style="
                                background: #00e6e6;
                                color: #000;
                                border: none;
                                border-radius: 8px;
                                padding: 0 20px;
                                font-weight: bold;
                                cursor: pointer;
                                transition: all 0.3s;
                            ">
                                Copy
                            </button>
                        </div>
                    </div>
                    <div style="background: rgba(0, 230, 230, 0.1); border-left: 3px solid #00e6e6; padding: 12px 15px; margin-top: 20px;">
                        <p style="margin: 0; font-size: 14px; color: #00e6e6;">
                            <i class="fas fa-gift" style="margin-right: 8px;"></i>
                            Your friend gets 100 $LUCK when they sign up using your code!
                        </p>
                    </div>
                </div>
                <div style="text-align: center; margin-top: 25px;">
                    <p style="color: #aaa; margin-bottom: 15px;">Share via:</p>
                    <div style="display: flex; gap: 15px; justify-content: center;">
                        <button class="share-btn" data-platform="whatsapp">
                            <i class="fab fa-whatsapp"></i> WhatsApp
                        </button>
                        <button class="share-btn" data-platform="telegram">
                            <i class="fab fa-telegram"></i> Telegram
                        </button>
                        <button class="share-btn" data-platform="copy">
                            <i class="fas fa-link"></i> Copy Link
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .share-btn {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
            border-radius: 6px;
            padding: 8px 15px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }
        .share-btn:hover {
            background: rgba(0, 230, 230, 0.2);
            border-color: #00e6e6;
        }
        .share-btn i {
            font-size: 16px;
        }
        #copyReferralBtn.copied {
            background: #4CAF50;
        }
    </style>

    <script>
        // Function to format wallet address for display
        function formatWalletAddress(address) {
            if (!address) return '';
            if (address.length <= 12) return address;
            return address.substring(0, 6) + '...' + address.substring(address.length - 4);
        }

        // Function to update the displayed account ID - Modified to prevent overriding with hardcoded value
        function updateAccountId(accountId) {
            // Only update if the element exists and doesn't already have a value from PHP
            const userAccountIdElement = document.getElementById('userAccountId');
            if (userAccountIdElement && !userAccountIdElement.dataset.initialized) {
                userAccountIdElement.textContent = '' + accountId;
                userAccountIdElement.dataset.initialized = 'true';
            }
            
            const modalUserIdDisplayElement = document.getElementById('modalUserIdDisplay');
            if (modalUserIdDisplayElement) {
                modalUserIdDisplayElement.textContent = accountId; // Set text content for the modal
            }
        }

        // Function to update the displayed account amount
        function updateAccountAmount(amount) {
            const currentAccountAmountElement = document.getElementById('currentAccountAmount');
            if (currentAccountAmountElement) {
                currentAccountAmountElement.textContent = parseFloat(amount).toFixed(2) + ' $LUCK';
            }
        }

        // Function to show a message box
        function showMessageBox(message, isError = false) {
            const messageBox = document.getElementById('messageBox');
            if (messageBox) {
                messageBox.textContent = message;
                messageBox.style.backgroundColor = isError ? '#ff4444' : '#008080';
                messageBox.classList.add('show');
                setTimeout(() => {
                    messageBox.classList.remove('show');
                }, 2000); // Hide after 2 seconds
            }
        }

        // Function to format transaction amount with sign
        function formatTransactionAmount(amount, direction, type = 'transfer', notes = '') {
            const isConversion = type.toLowerCase().includes('conversion');
            const amountNum = parseFloat(amount);
            
            // For conversion transactions, determine if it's converting to or from LUCK
            const isConvertingToLuck = notes.toLowerCase().includes('to luck') || type.toLowerCase().includes('php_to_luck');
            const isConvertingFromLuck = notes.toLowerCase().includes('from luck') || type.toLowerCase().includes('luck_to_php');
            
            console.log('Formatting amount:', { 
                amount, 
                direction, 
                type, 
                notes, 
                isConversion, 
                isConvertingToLuck, 
                isConvertingFromLuck 
            });
            
            // Determine the effective direction for display
            let effectiveDirection = direction;
            let displayAmount = amountNum;
            
            if (isConversion) {
                if (isConvertingToLuck) {
                    effectiveDirection = 'received';  // PHP to LUCK is receiving LUCK
                    displayAmount = Math.abs(amountNum);  // Ensure positive for receiving
                } else if (isConvertingFromLuck) {
                    effectiveDirection = 'sent';  // LUCK to PHP is sending LUCK
                    displayAmount = -Math.abs(amountNum);  // Ensure negative for sending
                }
            }
            
            // Format the amount with appropriate sign and color
            const isReceived = effectiveDirection === 'received';
            const sign = isReceived ? '+' : '-';
            const color = isReceived ? '#2ecc71' : '#ff6b6b';
            
            return `<span style="color: ${color}">${sign}${Math.abs(displayAmount).toFixed(2)} $LUCK</span>`;
        }

        // Function to format wallet address for display
        function formatAddress(address) {
            if (!address) return 'Unknown';
            if (address.length <= 10) return address;
            return `${address.substring(0, 6)}...${address.substring(address.length - 4)}`;
        }

        // Function to load transactions
        async function loadTransactions() {
            const transactionContent = document.getElementById('transactionContent');
            if (!transactionContent) return;

            // Show loading state
            transactionContent.innerHTML = '<div class="loading-spinner"><i class="fa-solid fa-spinner fa-spin"></i> Loading transactions...</div>';

            try {
                console.log('Fetching transactions from:', '/Luck%20Wallet/php/get_transactions.php?limit=50');
                const response = await fetch('/Luck%20Wallet/php/get_transactions.php?limit=50');
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();
                console.log('API Response:', result);

                // Check for transactions in both possible locations
                const transactions = result.transactions || (result.data && result.data.transactions) || [];
                console.log('Transactions array:', transactions);
                
                if (transactions.length > 0) {
                    console.log(`Found ${transactions.length} transactions`);
                    // Create transaction list
                    let html = `
                        <div class="transaction-header" style="display: flex; justify-content: space-between; align-items: center; margin: -10px 0 10px 0; padding-top: 5px;">
                            <h3 class="section-heading" style="margin: 0; font-size: 1.1em; color: #00e6e6; text-transform: uppercase; letter-spacing: 1px; font-weight: 600;">TRANSACTION HISTORY</h3>
                            <div class="tx-count" style="background: rgba(0, 230, 230, 0.2); padding: 4px 12px; border-radius: 15px; font-size: 0.9em; color: #00e6e6; font-weight: 500;">
                                ${transactions.length} ${transactions.length === 1 ? 'Transaction' : 'Transactions'}
                            </div>
                        </div>
                        <div class="transactions-list" style="max-height: 470px; overflow-y: auto; padding-right: 5px; margin-top: 5px; scrollbar-width: none; -ms-overflow-style: none;">
                        <style>
                            .transactions-list::-webkit-scrollbar {
                                display: none;
                            }
                        </style>
                            ${transactions.map(tx => {
                                console.log('Processing transaction:', tx);
                                const direction = tx.direction || 'received';
                                // For system-generated transactions, show 'System' as the sender
                                const isSystemTransaction = !tx.other_party || tx.type === 'system';
                                const otherParty = isSystemTransaction ? 'System' : tx.other_party;
                                const amount = tx.amount || 0;
                                const date = tx.date || 'Unknown date';
                                const notes = tx.notes || 'Transaction';
                                const txType = tx.type || 'transfer';
                                const txTypeFormatted = txType.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                                
                                const isConversion = txType.toLowerCase().includes('conversion');
                                // For conversion transactions, determine if it's converting to or from LUCK
                                const isConvertingToLuck = txType.toLowerCase().includes('php_to_luck') || notes.toLowerCase().includes('to luck');
                                const isConvertingFromLuck = txType.toLowerCase().includes('luck_to_php') || notes.toLowerCase().includes('from luck');
                                
                                // Determine the effective direction for display
                                let effectiveDirection = direction;
                                
                                if (isConversion) {
                                    if (isConvertingToLuck) {
                                        effectiveDirection = 'received';  // PHP to LUCK is receiving LUCK
                                    } else if (isConvertingFromLuck) {
                                        effectiveDirection = 'sent';  // LUCK to PHP is sending LUCK
                                    } else if (direction === 'conversion') {
                                        // For self-conversions, check the amount sign
                                        effectiveDirection = amount >= 0 ? 'received' : 'sent';
                                    }
                                }
                                
                                // For conversion transactions, use the amount as is (already in LUCK)
                                // The backend now sends the correct LUCK amount for both directions
                                const displayAmount = parseFloat(amount);
                                
                                console.log('Transaction:', { 
                                    amount, 
                                    originalDirection: direction,
                                    effectiveDirection,
                                    type: txType, 
                                    notes,
                                    isConversion,
                                    isConvertingToLuck,
                                    isConvertingFromLuck
                                });
                                
                                return `
                                <div class="transaction-item ${effectiveDirection}${isConversion ? ' conversion' : ''}" 
                                     data-type="${isConversion ? 'conversion' : ''}" 
                                     data-conversion-direction="${isConvertingToLuck ? 'to-luck' : (isConvertingFromLuck ? 'from-luck' : '')}"
                                     title="${notes}"
                                     data-amount="${amount}"
                                     data-direction="${effectiveDirection}">
                                    <div class="tx-icon ${direction}">
                                        <i class="fa-solid fa-${isConversion ? 'exchange-alt' : (direction === 'sent' ? 'arrow-up' : 'arrow-down')}"></i>
                                    </div>
                                    <div class="tx-details">
                                        <div class="tx-address">
                                            <i class="fa-solid fa-${direction === 'sent' ? 'arrow-up-right' : 'arrow-down-left'}"></i>
                                            ${isSystemTransaction ? 'System' : `${direction === 'sent' ? 'To' : 'From'}: ${formatAddress(otherParty)}`}
                                            <span class="tx-status completed">${txTypeFormatted}</span>
                                        </div>
                                        <div class="tx-date">
                                            <i class="far fa-clock"></i>
                                            ${date}
                                        </div>
                                    </div>
                                    <div class="tx-amount ${isConversion ? 'conversion' : direction}">
                                        ${formatTransactionAmount(displayAmount, effectiveDirection, txType, notes)}
                                    </div>
                                </div>`;
                            }).join('')}
                        </div>
                    `;
                    transactionContent.innerHTML = html;
                } else {
                    transactionContent.innerHTML = `
                        <h3 class="section-heading">TRANSACTION HISTORY</h3>
                        <div class="no-transactions">
                            <i class="fa-solid fa-inbox"></i>
                            <p>No transactions yet</p>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error loading transactions:', error);
                transactionContent.innerHTML = `
                    <h3 class="section-heading">TRANSACTION HISTORY</h3>
                    <div class="error-message">
                        <i class="fa-solid fa-exclamation-triangle"></i>
                        <p>Failed to load transactions. Please try again later.</p>
                    </div>
                `;
            }
        }

        // Function to load and display the leaderboard
        async function loadLeaderboard() {
            const leaderboardContent = document.getElementById('leaderboardContent');
            if (!leaderboardContent) {
                console.error('Leaderboard content element not found');
                return;
            }

            const loadingElement = leaderboardContent.querySelector('.leaderboard-loading');
            const listElement = document.getElementById('leaderboardList');
            const errorElement = document.getElementById('leaderboardError');
            const tbody = document.getElementById('leaderboardBody');
            
            // Reset states
            if (loadingElement) loadingElement.style.display = 'block';
            if (listElement) listElement.style.display = 'none';
            if (errorElement) errorElement.style.display = 'none';
            
            try {
                // Add timestamp to prevent caching
                const timestamp = new Date().getTime();
                const response = await fetch(`php/get_leaderboard.php?_=${timestamp}`, {
                    method: 'GET',
                    headers: {
                        'Cache-Control': 'no-cache, no-store, must-revalidate',
                        'Pragma': 'no-cache',
                        'Expires': '0',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                });

                // Check if we got a valid response
                if (!response.ok) {
                    throw new Error(`Server error: ${response.status}`);
                }

                // Parse JSON response
                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.error || 'Failed to load leaderboard data');
                }
                
                // Clear existing rows
                if (tbody) tbody.innerHTML = '';
                
                // Add leaderboard rows if we have data
                if (data.data && Array.isArray(data.data) && data.data.length > 0) {
                    data.data.forEach(user => {
                        if (!user) return;
                        
                        const row = document.createElement('tr');
                        row.className = `leaderboard-row rank-${Math.min(user.rank, 3)}`; // Style top 3 ranks
                        
                        // Add rank cell with badge
                        const rankCell = document.createElement('td');
                        rankCell.style.padding = '10px';
                        rankCell.style.verticalAlign = 'middle';
                        rankCell.innerHTML = `
                            <div style="
                                width: 32px; 
                                height: 32px; 
                                background: #00e6e6; 
                                border-radius: 50%; 
                                display: flex; 
                                align-items: center; 
                                justify-content: center;
                                color: #000;
                                font-weight: bold;
                            ">
                                ${user.rank}
                            </div>
                        `;
                        
                        // Add user cell with username and wallet
                        const userCell = document.createElement('td');
                        userCell.style.padding = '10px';
                        userCell.style.verticalAlign = 'middle';
                        userCell.innerHTML = `
                            <div>
                                <div style="font-weight: 500;">${escapeHtml(user.username || 'Anonymous')}</div>
                                <div style="font-size: 0.85em; color: #aaa; font-family: monospace;">${escapeHtml(user.wallet_address || '')}</div>
                            </div>
                        `;
                        
                        // Add balance cell
                        const balanceCell = document.createElement('td');
                        balanceCell.style.padding = '10px';
                        balanceCell.style.textAlign = 'right';
                        balanceCell.style.verticalAlign = 'middle';
                        balanceCell.style.fontWeight = '500';
                        balanceCell.style.fontFamily = 'monospace';
                        balanceCell.textContent = `${user.balance} $LUCK`;
                        
                        // Append cells to row
                        row.appendChild(rankCell);
                        row.appendChild(userCell);
                        row.appendChild(balanceCell);
                        
                        // Add row to table
                        if (tbody) tbody.appendChild(row);
                    });
                    
                    // Show the list and hide loading
                    if (loadingElement) loadingElement.style.display = 'none';
                    if (listElement) listElement.style.display = 'block';
                } else {
                    throw new Error('No leaderboard data available');
                }
                
            } catch (error) {
                console.error('Error loading leaderboard:', error);
                if (loadingElement) loadingElement.style.display = 'none';
                if (listElement) listElement.style.display = 'none';
                if (errorElement) {
                    errorElement.style.display = 'block';
                    errorElement.innerHTML = `
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>${escapeHtml(error.message || 'Failed to load leaderboard')}</div>
                        <button onclick="loadLeaderboard()" class="retry-button" style="
                            margin-top: 10px; 
                            padding: 8px 16px; 
                            background: #00e6e6; 
                            border: none; 
                            border-radius: 4px; 
                            cursor: pointer; 
                            color: #000;
                        ">
                            <i class="fas fa-sync-alt"></i> Try Again
                        </button>
                    `;
                }
            }
        }
        
        // Helper function to escape HTML
        function escapeHtml(unsafe) {
            if (typeof unsafe !== 'string') return '';
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        // Function to load user's NFT collection
        async function loadNFTCollection() {
            const collectionContainer = document.getElementById('nftCollection');
            if (!collectionContainer) {
                console.error('NFT collection container not found');
                return;
            }

            // Show loading state with animation
            collectionContainer.innerHTML = `
                <div style="grid-column: 1 / -1; text-align: center; padding: 40px 20px;">
                    <div class="spinner-border" role="status" style="color: #00e6e6; width: 2.5rem; height: 2.5rem; border-width: 0.25em;">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3" style="color: #a0aec0; font-size: 1.1em; font-weight: 500;">Loading your NFT collection...</p>
                    <p style="font-size: 0.9em; color: #888; margin-top: 10px;">This may take a moment...</p>
                </div>
            `;

            try {
                // Add timestamp to prevent caching
                const timestamp = new Date().getTime();
                console.log('Fetching NFTs from: php/get_nfts.php');
                
                const response = await fetch(`php/get_nfts.php?_=${timestamp}`, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'Cache-Control': 'no-cache, no-store, must-revalidate',
                        'Pragma': 'no-cache',
                        'Expires': '0'
                    },
                    credentials: 'same-origin'
                });
                
                console.log('Response status:', response.status, response.statusText);
                
                // Get the response text first to check if it's valid JSON
                const responseText = await response.text();
                let result;
                
                try {
                    result = JSON.parse(responseText);
                } catch (e) {
                    console.error('Failed to parse JSON:', e, 'Response text:', responseText);
                    throw new Error(`Failed to parse server response. The server might be returning an error page.`);
                }
                
                console.log('Parsed response:', result);
                
                if (!result.success) {
                    throw new Error(result.error || 'Invalid response from server');
                }
                
                const nfts = result.data || [];
                
                // Debug: Log the first NFT to check its data
                if (nfts.length > 0) {
                    console.log('First NFT data:', nfts[0]);
                }
                
                // Update the NFT count in the header
                const nftCountElement = document.getElementById('nftCount');
                if (nftCountElement) {
                    nftCountElement.textContent = nfts.length;
                }
                
                // Log debug info if available
                if (result.debug) {
                    console.log('Debug info:', result.debug);
                }

                console.log('Loaded NFTs:', nfts);

                // Apply grid styles directly to the collection container with fixed column width
                collectionContainer.style.display = 'grid';
                collectionContainer.style.gridTemplateColumns = 'repeat(auto-fill, 180px)'; // Fixed width columns
                collectionContainer.style.justifyContent = 'center'; // Center the grid items
                collectionContainer.style.gap = '15px';
                collectionContainer.style.width = '100%';
                collectionContainer.style.maxWidth = '100%';
                collectionContainer.style.padding = '0 15px 15px';
                collectionContainer.style.boxSizing = 'border-box';
                collectionContainer.style.overflowY = 'auto';
                collectionContainer.style.height = '100%';
                collectionContainer.style.alignContent = 'flex-start';
                collectionContainer.style.scrollbarWidth = 'none'; // Firefox   
                collectionContainer.style.msOverflowStyle = 'none'; // IE and Edge
                
                // Hide scrollbar for WebKit browsers (Chrome, Safari, etc.)
                const style = document.createElement('style');
                style.textContent = `
                    #nftCollection::-webkit-scrollbar {
                        display: none;
                    }
                    
                    @media (max-width: 768px) {
                        #nftCollection {
                            grid-template-columns: repeat(auto-fill, 160px);
                            gap: 12px;
                            padding: 0 10px;
                        }
                    }
                    
                    @media (max-width: 480px) {
                        #nftCollection {
                            grid-template-columns: repeat(2, 1fr);
                            gap: 10px;
                            padding: 0 8px;
                        }
                        
                        #nftCollection .nft-card {
                            width: 100%;
                            min-width: 0;
                        }
                    }`;
                document.head.appendChild(style);
                
                if (nfts.length > 0) {
                    collectionContainer.innerHTML = `
                        <style>
                            .nft-card {
                                background: rgba(0, 0, 0, 0.5);
                                border-radius: 12px;
                                overflow: hidden;
                                width: 180px; /* Fixed width */
                                cursor: pointer;
                                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                                border: 1px solid rgba(0, 230, 230, 0.3);
                                position: relative;
                                transform: translateY(0);
                                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                                z-index: 1;
                                display: flex;
                                flex-direction: column;
                                height: 280px; /* Fixed height */
                                flex-shrink: 0;
                                margin: 0; /* Remove any default margins */
                            }
                            
                            body.light-mode .nft-card {
                                background: rgba(255, 255, 255, 0.95);
                                border: 1px solid rgba(0, 51, 102, 0.2);
                                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
                            }
                            .nft-card:hover {
                                transform: translateY(-5px);
                                box-shadow: 0 10px 20px rgba(0, 230, 230, 0.2);
                                border-color: rgba(0, 230, 230, 0.7);
                                z-index: 2;
                            }
                            .nft-image-container {
                                position: relative;
                                width: 100%;
                                height: 200px;
                                overflow: hidden;
                                background: #0a0f1a;
                                flex-shrink: 0;
                            }
                            body.light-mode .nft-image-container {
                                background: #ffffff;
                            }
                            .nft-image-container img {
                                position: absolute;
                                top: 0;
                                left: 0;
                                width: 100%;
                                height: 100%;
                                object-fit: contain;
                                transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
                                will-change: transform;
                            }
                            .nft-card:hover .nft-image-container img {
                                transform: scale(1.1);
                            }
                            .nft-overlay {
                                position: absolute;
                                bottom: 0;
                                left: 0;
                                right: 0;
                                background: linear-gradient(to top, rgba(0,0,0,0.8) 0%, rgba(0,0,0,0) 100%);
                                padding: 15px;
                                color: white;
                                z-index: 2;
                                transform: translateY(100%);
                                transition: transform 0.3s ease;
                            }
                            .nft-card:hover .nft-overlay {
                                transform: translateY(0);
                            }
                            .nft-details {
                                padding: 12px 12px 12px 8px; /* Reduced left padding from 12px to 8px */
                                background: rgba(0, 0, 0, 0.7);
                                border-top: 1px solid rgba(0, 230, 230, 0.1);
                                transition: all 0.3s ease;
                                flex: 1;
                                display: flex;
                                flex-direction: column;
                                justify-content: space-between;
                            }
                            .nft-card:hover .nft-details {
                                background: rgba(0, 0, 0, 0.9);
                            }
                            body.light-mode .nft-details {
                                background: rgba(30, 41, 59, 0.95);
                                border-top: 1px solid rgba(148, 163, 184, 0.2);
                                color: #ffffff;
                            }
                            body.light-mode .nft-collection,
                            body.light-mode .nft-name,
                            body.light-mode .nft-meta,
                            body.light-mode .nft-status {
                                color: #ffffff !important;
                            }
                            body.light-mode .nft-card:hover .nft-details {
                                background: rgba(15, 23, 42, 0.98);
                            }
                            body.light-mode .nft-card .nft-name {
                                color: #ffffff !important;
                            }
                            .nft-name {
                                font-size: 0.95em;
                                font-weight: 500;
                                color: #ffffff;
                                white-space: nowrap;
                                overflow: hidden;
                                text-overflow: ellipsis;
                                margin-bottom: 5px;
                                transition: color 0.3s ease;
                            }
                            body.light-mode .nft-name {
                                color: #003366;
                            }
                            .nft-card:hover .nft-name {
                                color: #00ffff;
                            }
                            .nft-meta {
                                display: flex;
                                justify-content: space-between;
                                align-items: center;
                                font-size: 0.85em;
                                width: calc(100% + 8px); /* Extend width to compensate for negative margin */
                                gap: 8px;
                                margin-left: -8px; /* Increased negative margin to move more left */
                            }
                            .nft-collection {
                                color: #ffffff;
                                white-space: nowrap;
                                overflow: hidden;
                                text-overflow: ellipsis;
                                margin-right: 8px;
                                display: block;
                                flex: 1;
                                min-width: 0;
                                font-family: 'Fira Code', monospace;
                                font-size: 0.8em;
                                opacity: 0.9;
                            }
                            .nft-status {
                                display: flex;
                                align-items: center;
                                padding: 2px 8px;
                                border-radius: 10px;
                                font-size: 0.8em;
                                font-weight: 600;
                                white-space: nowrap;
                                flex-shrink: 0;
                            }
                            .nft-price {
                                color: #00e6e6;
                                background: rgba(0, 230, 230, 0.1);
                                border: 1px solid rgba(0, 230, 230, 0.2);
                                padding: 4px 8px;
                                border-radius: 4px;
                                font-size: 0.8em;
                                font-weight: 600;
                            }
                            .nft-unlisted {
                                color: #a0aec0;
                                background: rgba(160, 174, 192, 0.1);
                                border: 1px solid rgba(160, 174, 192, 0.15);
                            }
                            .nft-glow {
                                position: absolute;
                                top: 0;
                                left: 0;
                                right: 0;
                                height: 100%;
                                border-radius: 12px;
                                box-shadow: 0 0 15px rgba(0, 230, 230, 0);
                                transition: box-shadow 0.3s ease;
                                pointer-events: none;
                                z-index: -1;
                            }
                            .nft-card:hover .nft-glow {
                                box-shadow: 0 0 30px rgba(0, 230, 230, 0.3);
                            }
                            body.light-mode .nft-card:hover .nft-glow {
                                box-shadow: 0 0 30px rgba(0, 102, 153, 0.2);
                            }
                        </style>
                        ${nfts.map(nft => `
                            <div class="nft-card" onclick="window.location.href='nft_details.php?id=${nft.id}'">
                                <div class="nft-image-container">
                                    <img src="${nft.image_url || 'img/default-nft.svg'}" 
                                         onerror="this.onerror=null; this.src='img/default-nft.svg';"
                                         alt="${nft.name || 'NFT'}">
                                    <div class="nft-overlay">
                                        <div class="nft-name">${nft.name || nft.collection_name || 'Unnamed NFT'}</div>
                                        <div class="nft-price">
                                            ${nft.is_listed ? `${nft.price_luck || '0'} LUCK` : 'Not for Sale'}
                                        </div>
                                    </div>
                                </div>
                                <div class="nft-details">
                                    <div class="nft-name">${nft.name === 'Unnamed NFT' && nft.collection_name ? nft.collection_name : nft.name || 'Unnamed NFT'}</div>
                                    <div class="nft-meta">
                                        <span class="nft-id" style="color: #ffffff; margin-left: 0;">ID: #${nft.id || 'N/A'}</span>
                                        <span class="nft-status ${nft.is_listed ? 'nft-price' : 'nft-unlisted'}">
                                            ${nft.is_listed ? 'Listed' : 'Unlisted'}
                                        </span>
                                    </div>
                                </div>
                                <div class="nft-glow"></div>
                            </div>`).join('')}
                    `;
                } else {
                    // No NFTs found - reset styles for empty state
                    collectionContainer.style.display = 'flex';
                    collectionContainer.style.alignItems = 'center';
                    collectionContainer.style.justifyContent = 'center';
                    collectionContainer.style.padding = '40px 20px';
                    collectionContainer.style.textAlign = 'center';
                    collectionContainer.style.gridTemplateColumns = 'none';
                    collectionContainer.style.gap = '0';
                    collectionContainer.style.alignContent = 'center';
                    
                    collectionContainer.innerHTML = `
                        <div style="max-width: 400px; width: 100%; display: flex; flex-direction: column; align-items: center;">
                            <div style="
                                width: 100px;
                                height: 100px;
                                margin: 0 auto 20px;
                                border-radius: 50%;
                                background: rgba(0, 230, 230, 0.1);
                                display: flex;
                                align-items: center;
                                justify-content: center;">
                                <i class="fas fa-image" style="font-size: 2.5em; color: #4a5568;"></i>
                            </div>
                            <h3 style="color: #e2e8f0; margin: 0 0 10px 0; padding: 0; font-size: 1.3em; text-align: center; width: 100%;">No NFTs Found</h3>
                            <p style="color: #a0aec0; margin-bottom: 20px; line-height: 1.5;">
                                You don't have any NFTs in your collection yet. Start by creating or purchasing your first NFT!
                            </p>
                            <a href="add_nft.php" class="btn btn-primary" style="
                                display: inline-flex;
                                align-items: center;
                                background: #00e6e6;
                                color: #000;
                                border: none;
                                padding: 10px 24px;
                                border-radius: 8px;
                                font-weight: 600;
                                text-decoration: none;
                                transition: all 0.2s;">
                                <i class="fas fa-plus" style="margin-right: 8px;"></i>
                                Create Your First NFT
                            </a>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error loading NFT collection:', error);
                
                // Show error message with retry button
                collectionContainer.innerHTML = `
                    <div style="grid-column: 1 / -1; text-align: center; padding: 40px 20px;">
                        <div style="max-width: 400px; margin: 0 auto;">
                            <div style="
                                width: 80px;
                                height: 80px;
                                margin: 0 auto 20px;
                                border-radius: 50%;
                                background: rgba(239, 68, 68, 0.1);
                                display: flex;
                                align-items: center;
                                justify-content: center;
                            ">
                                <i class="fas fa-exclamation-triangle" style="font-size: 2em; color: #f56565;"></i>
                            </div>
                            <h3 style="color: #feb2b2; margin-bottom: 10px; font-size: 1.3em;">Failed to Load Collection</h3>
                            <p style="color: #e2e8f0; margin-bottom: 5px;">
                                ${error.message || 'An unknown error occurred while loading your collection.'}
                            </p>
                            <p style="color: #a0aec0; font-size: 0.9em; margin-bottom: 20px;">
                                Please check your internet connection and try again.
                            </p>
                            <button onclick="loadNFTCollection()" style="
                                display: inline-flex;
                                align-items: center;
                                background: #e53e3e;
                                color: white;
                                border: none;
                                padding: 10px 24px;
                                border-radius: 8px;
                                font-weight: 500;
                                cursor: pointer;
                                transition: all 0.2s;
                                margin: 0 5px;
                            ">
                                <i class="fas fa-sync-alt" style="margin-right: 8px;"></i>
                                Try Again
                            </button>
                        </div>
                    </div>
                `;
                
                // Log detailed error in console
                if (error.response) {
                    console.error('Error response:', error.response);
                } else if (error.request) {
                    console.error('No response received:', error.request);
                } else {
                    console.error('Error:', error.message);
                }
            }
        }


        // Function to update the daily login button state
        function updateDailyLoginButton(canClaim) {
            const loginButton = document.getElementById('dailyLoginButton');
            console.log('updateDailyLoginButton called with canClaim:', canClaim, 'button exists:', !!loginButton);
            
            if (!loginButton) {
                console.warn('Daily login button not found!');
                return;
            }
            
            console.log('Current button state - disabled:', loginButton.disabled, 
                        'className:', loginButton.className, 
                        'innerHTML:', loginButton.innerHTML);
            
            if (canClaim) {
                loginButton.disabled = false;
                loginButton.innerHTML = '<i class="fas fa-gift"></i> Claim';
                loginButton.className = 'claim-button';
                console.log('Button updated to ENABLED state');
            } else {
                loginButton.disabled = true;
                loginButton.innerHTML = '<i class="fas fa-check-circle"></i> Claimed';
                loginButton.className = 'claim-button claimed';
                console.log('Button updated to DISABLED state');
            }
            
            // Log the final state
            console.log('Final button state - disabled:', loginButton.disabled, 
                       'className:', loginButton.className);
        }

        // Function to check if daily bonus has been claimed today
        async function checkDailyBonusStatus() {
            const loginButton = document.getElementById('dailyLoginButton');
            console.log('checkDailyBonusStatus called, loginButton:', loginButton);
            if (!loginButton) {
                console.warn('No login button found, returning default canClaim:true');
                return { canClaim: true };
            }
            
            try {
                console.log('Fetching daily bonus status...');
                const timestamp = new Date().getTime();
                const response = await fetch(`php/check_daily_bonus.php?_=${timestamp}`, {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {
                        'Cache-Control': 'no-cache, no-store, must-revalidate',
                        'Pragma': 'no-cache',
                        'Expires': '0'
                    }
                });
                
                console.log('Response status:', response.status);
                
                if (!response.ok) {
                    const errorText = await response.text();
                    console.error('Error response:', errorText);
                    throw new Error(`Failed to check daily bonus status: ${response.status} ${response.statusText}`);
                }
                
                const data = await response.json();
                console.log('Daily bonus status from server:', data);
                
                // Update the button state based on the response
                const canClaim = data.canClaim === true;
                console.log('Updating login button, canClaim:', canClaim);
                updateDailyLoginButton(canClaim);
                
                return data;
            } catch (error) {
                console.error('Error checking daily bonus status:', error);
                // Default to not allowing claim if there's an error
                console.log('Error occurred, setting canClaim to false');
                updateDailyLoginButton(false);
                return { canClaim: false, error: error.message };
            }
        }
        
        // Function to set up eCasino button - completely locked to 'Go' state
        function setupECasinoButton() {
            console.log('Setting up eCasino button...');
            const eCasinoButton = document.getElementById('eCasinoButton');
            if (!eCasinoButton) {
                console.error('eCasino button not found');
                return;
            }
            
            // Log current state before making changes
            console.log('Current button state:', {
                id: eCasinoButton.id,
                text: eCasinoButton.textContent,
                class: eCasinoButton.className,
                hasClickHandlers: eCasinoButton.onclick !== null || 
                                 eCasinoButton.hasAttribute('onclick') ||
                                 eCasinoButton.getAttribute('listener') === 'true'
            });
            
            // Create a new button to replace the existing one (removes all event listeners)
            const newButton = document.createElement('button');
            newButton.id = 'eCasinoButton';
            newButton.className = 'claim-button';
            newButton.textContent = 'Go';
            
            // Set up the click handler
            const clickHandler = (e) => {
                console.log('eCasino button clicked - opening eCasino in new tab');
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                window.open('http://localhost/Luck%20Wallet/ecasino/index.php', '_blank');
                return false;
            };
            
            // Add the click handler in a way that can't be easily removed
            newButton.addEventListener('click', clickHandler, true);
            
            // Add a marker to detect if this is our button
            newButton.setAttribute('data-button-type', 'ecasino-go');
            
            // Replace the old button with our new one
            eCasinoButton.parentNode.replaceChild(newButton, eCasinoButton);
            
            // Lock the button properties
            Object.defineProperty(newButton, 'textContent', {
                set: function(value) {
                    console.log('Attempt to set textContent to:', value);
                    // Allow setting to 'Go' but block any other changes
                    if (value !== 'Go') {
                        console.warn('Blocked attempt to change button text from "Go" to:', value);
                        return;
                    }
                    Object.getOwnPropertyDescriptor(HTMLButtonElement.prototype, 'textContent')
                          .set.call(this, value);
                },
                get: function() { 
                    return 'Go'; 
                },
                configurable: false
            });
            
            // Prevent any other code from modifying our button
            Object.freeze(newButton);
            console.log('eCasino button setup complete');
            
            // Set up a mutation observer to detect any changes to the button
            const observer = new MutationObserver((mutations) => {
                console.log('Mutation observed on eCasino button:', mutations);
                // If the button text changes, change it back to 'Go'
                if (newButton.textContent !== 'Go') {
                    console.log('Button text changed to', newButton.textContent, '- resetting to "Go"');
                    newButton.textContent = 'Go';
                }
            });
            
            // Start observing the button for changes
            observer.observe(newButton, {
                childList: true,
                subtree: true,
                characterData: true,
                attributes: true
            });
            
            // Store the observer on the button so it doesn't get garbage collected
            newButton._observer = observer;
        }
        
        // Function to handle daily login button
        function updateTaskButtons() {
            const loginButton = document.getElementById('dailyLoginButton');
            if (!loginButton) return;
            
            // Check daily bonus status
            checkDailyBonusStatus();
            
            // Handle daily login button
            loginButton.onclick = async function() {
                const button = this;
                if (button.disabled) return;
                
                // Disable button immediately to prevent double-clicks
                const originalHTML = button.innerHTML;
                button.disabled = true;
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Claiming...';
                
                try {
                    console.log('Sending request to claim_daily_bonus.php');
                    const response = await fetch('php/claim_daily_bonus.php', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Cache-Control': 'no-cache, no-store, must-revalidate',
                            'Pragma': 'no-cache',
                            'Expires': '0'
                        }
                    });
                    
                    console.log('Response status:', response.status);
                    
                    if (!response.ok) {
                        const errorText = await response.text();
                        throw new Error(`HTTP error! status: ${response.status}, body: ${errorText}`);
                    }
                    
                    const data = await response.json().catch(e => {
                        console.error('Failed to parse JSON response:', e);
                        throw new Error('Invalid response from server');
                    });
                    
                    console.log('Claim response:', data);
                    
                    if (data.success) {
                        // Update UI to show success
                        updateDailyLoginButton(false);
                        
                        // Show success message
                        showNotification('success', data.message || 'Daily bonus claimed successfully!');
                        
                        // Update balance display if new balance is returned
                        if (data.newBalance !== undefined) {
                            updateAccountAmount(data.newBalance);
                        }
                        
                        // Reload transactions to show the new transaction
                        loadTransactions();
                    } else {
                        // Re-enable button if claim failed
                        throw new Error(data.message || 'Failed to claim daily bonus');
                    }
                } catch (error) {
                    console.error('Error claiming daily bonus:', error);
                    // Don't re-enable the button - let the periodic check handle it
                    showNotification('error', error.message || 'An error occurred while claiming your bonus. Please try again.');
                    // Force a status check to ensure button state is correct
                    setTimeout(checkDailyBonusStatus, 1000);
                } finally {
                    // Button state will be updated by checkDailyBonusStatus
                }
            };
            
            // eCasino button is handled by checkECasinoStatus()
        }

        // Check for eCasino visit status when returning from eCasino
        function checkReturnFromECasino() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('from') === 'ecasino') {
                // Mark eCasino as visited today
                localStorage.setItem('lastECasinoVisit', Date.now().toString());
                // Update buttons
                updateTaskButtons();
                // Remove the parameter from URL
                const newUrl = window.location.pathname;
                window.history.replaceState({}, document.title, newUrl);
            }
        }
        
        // Function to show notification
        function showNotification(type, message) {
            // You can implement a more sophisticated notification system here
            // For now, we'll use a simple alert
            alert(`${type.toUpperCase()}: ${message}`);
        }

        document.addEventListener('DOMContentLoaded', async () => {
            // Initialize task buttons
            updateTaskButtons();
            
            // Check daily bonus status after buttons are initialized
            try {
                const status = await checkDailyBonusStatus();
                console.log('Daily bonus status on load:', status);
                // Update the button state based on the status
                updateDailyLoginButton(status.canClaim);
            } catch (error) {
                console.error('Error checking daily bonus status on load:', error);
                // Default to not allowing claim if there's an error
                updateDailyLoginButton(false);
            }
            // Get the real wallet address from the PHP-initialized element
            const userAccountIdElement = document.getElementById('userAccountId');
            if (userAccountIdElement) {
                const walletAddress = userAccountIdElement.textContent.trim();
                updateAccountId(walletAddress);
                
                // Update the balance if available from PHP
                <?php if (isset($currentUser['luck_balance'])): ?>
                updateAccountAmount(<?php echo $currentUser['luck_balance']; ?>);
                <?php endif; ?>
                
                // Load transactions and NFT collection
                loadTransactions();
                loadNFTCollection();
                
                // Set up eCasino button last to ensure it overrides any other changes
                setupECasinoButton();
            }

            // User Profile Bubble and Dropdown Logic
            const userProfileBubble = document.getElementById('userProfileBubble');
            const dropdownContent = userProfileBubble.querySelector('.dropdown-content');

            if (userProfileBubble && dropdownContent) {
                userProfileBubble.addEventListener('click', function(event) {
                    event.stopPropagation();
                    dropdownContent.classList.toggle('show');
                });

                document.addEventListener('click', function(event) {
                    if (!userProfileBubble.contains(event.target)) {
                        dropdownContent.classList.remove('show');
                    }
                });
            }

            // Leaderboard Modal Logic
            const leaderboardButton = document.querySelector('.trophy-button');
            const leaderboardModal = document.getElementById('leaderboardModal');
            
            if (leaderboardButton && leaderboardModal) {
                leaderboardButton.addEventListener('click', () => {
                    leaderboardModal.classList.add('show');
                    console.log('Leaderboard button clicked! Showing modal.');
                    // Load leaderboard data when modal is opened
                    loadLeaderboard();
                });
            }

            // Deposit Modal Logic
            const depositButton = document.getElementById('depositButton');
            const depositModal = document.getElementById('depositModal');
            const copyUserIdButton = document.getElementById('copyUserIdButton');

            if (depositButton && depositModal) {
                depositButton.addEventListener('click', () => {
                    depositModal.classList.add('show');
                    console.log('Deposit button clicked! Showing deposit modal.');
                });
            }

            // Send Modal Logic (New)
            const sendButton = document.getElementById('sendButton');
            const sendModal = document.getElementById('sendModal');
            const sendFundsButton = document.getElementById('sendFundsButton');
            const recipientIdInput = document.getElementById('recipientIdInput');
            const amountToSendInput = document.getElementById('amountToSendInput');

            if (sendButton && sendModal) {
                sendButton.addEventListener('click', () => {
                    sendModal.classList.add('show');
                    console.log('Send button clicked! Showing send modal.');
                });
            }

            if (sendFundsButton) {
                sendFundsButton.addEventListener('click', async () => {
                    const recipientAddress = recipientIdInput.value.trim();
                    const amount = parseFloat(amountToSendInput.value);

                    // Validate inputs
                    if (!recipientAddress) {
                        showMessageBox('Please enter recipient wallet address', true);
                        return;
                    }
                    
                    if (isNaN(amount) || amount <= 0) {
                        showMessageBox('Please enter a valid amount', true);
                        return;
                    }

                    // Show loading state
                    const originalButtonText = sendFundsButton.innerHTML;
                    sendFundsButton.disabled = true;
                    sendFundsButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sending...';

                    try {
                        // Log the request data
                        console.log('Sending transaction:', { recipientAddress, amount });
                        
                        // Create form data for the request
                        const formData = new FormData();
                        formData.append('receiverAddress', recipientAddress);
                        formData.append('amount', amount);
                        
                        // Send the request
                        const response = await fetch('/Luck%20Wallet/php/send_transaction.php', {
                            method: 'POST',
                            body: formData // Send as form data instead of JSON
                        });

                        // Parse the response
                        let result;
                        try {
                            result = await response.json();
                            console.log('Transaction response:', result);
                        } catch (e) {
                            console.error('Error parsing JSON response:', e);
                            throw new Error('Invalid response from server');
                        }

                        // Handle the response - check both root level and data.success
                        const isSuccess = result.success || (result.data && result.data.success);
                        const successMessage = result.message || result.data?.message || `Successfully sent ${amount} $LUCK`;
                        const errorMessage = result.message || result.data?.message || 'Transaction failed';
                        
                        if (isSuccess) {
                            showMessageBox(successMessage);
                            
                            // Close the modal and reset form immediately for better UX
                            sendModal.classList.remove('show');
                            recipientIdInput.value = '';
                            amountToSendInput.value = '';
                            
                            // Function to refresh balance
                            const refreshBalance = async () => {
                                try {
                                    const response = await fetch('/Luck%20Wallet/php/get_balance.php');
                                    const data = await response.json();
                                    if (data.success && data.balance !== undefined) {
                                        updateAccountAmount(data.balance);
                                    }
                                } catch (e) {
                                    console.error('Error refreshing balance:', e);
                                }
                            };
                            
                            // Refresh balance immediately
                            await refreshBalance();
                            
                            // Refresh transaction list after a short delay
                            setTimeout(() => {
                                loadTransactions();
                                // Refresh balance again after transactions load
                                refreshBalance();
                            }, 300);
                        } else {
                            throw new Error(errorMessage);
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        showMessageBox('Network error. Please try again.', true);
                    } finally {
                        // Reset button state
                        sendFundsButton.disabled = false;
                        sendFundsButton.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Send Funds';
                    }
                });
            }

            // Close Modal Logic (for all modals using data-modal-id)
            document.querySelectorAll('.close-button').forEach(button => {
                button.addEventListener('click', (event) => {
                    const modalId = event.target.dataset.modalId;
                    const modalToClose = document.getElementById(modalId);
                    if (modalToClose) {
                        modalToClose.classList.remove('show');
                    }
                });
            });

            // Close modal if clicked outside the modal content for all modals
            document.querySelectorAll('.modal').forEach(modal => {
                modal.addEventListener('click', (event) => {
                    if (event.target === modal) {
                        modal.classList.remove('show');
                    }
                });
            });

            // Copy User ID to Clipboard Logic
            if (copyUserIdButton) {
                copyUserIdButton.addEventListener('click', () => {
                    const userIdElement = document.getElementById('modalUserIdDisplay');
                    if (userIdElement) {
                        const userId = userIdElement.textContent;
                        // Use document.execCommand('copy') as navigator.clipboard.writeText() might not work in iframes
                        const tempInput = document.createElement('textarea');
                        tempInput.value = userId;
                        document.body.appendChild(tempInput);
                        tempInput.select();
                        try {
                            document.execCommand('copy');
                            showMessageBox('ID copied to clipboard!');
                            console.log('User ID copied: ' + userId);
                        } catch (err) {
                            console.error('Failed to copy user ID: ', err);
                            showMessageBox('Failed to copy ID.');
                        } finally {
                            document.body.removeChild(tempInput);
                        }
                    }
                });
            }

            // --- Theme Toggle Logic ---
            const themeToggleButton = document.getElementById('themeToggleButton');
            const body = document.body;
            const sunIcon = '<i class="fas fa-sun"></i>';
            const moonIcon = '<i class="fas fa-moon"></i>';

            // Function to set the theme
            function setTheme(mode) {
                if (mode === 'light') {
                    body.classList.add('light-mode');
                    themeToggleButton.innerHTML = moonIcon; // Show moon icon for light mode
                } else {
                    body.classList.remove('light-mode');
                    themeToggleButton.innerHTML = sunIcon; // Show sun icon for dark mode
                }
                localStorage.setItem('theme', mode); // Save preference
            }

            // Check for saved theme preference on load
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme) {
                setTheme(savedTheme);
            } else {
                // Default to dark mode if no preference is saved
                setTheme('dark');
            }

            // Toggle theme on button click
            themeToggleButton.addEventListener('click', () => {
                if (body.classList.contains('light-mode')) {
                    setTheme('dark');
                } else {
                    setTheme('light');
                }
            });

            // Close modal when clicking outside or on close button
            document.addEventListener('click', function(event) {
                // Close when clicking outside modal content
                if (event.target.classList.contains('modal')) {
                    event.target.style.display = 'none';
                }
                // Close when clicking close button
                if (event.target.classList.contains('close-button')) {
                    const modalId = event.target.getAttribute('data-modal-id');
                    if (modalId) {
                        const modal = document.getElementById(modalId);
                        if (modal) {
                            modal.style.display = 'none';
                        }
                    }
                }
            });

            // Prevent modal from closing when clicking inside modal content
            document.querySelectorAll('.modal-content').forEach(modalContent => {
                modalContent.addEventListener('click', function(event) {
                    event.stopPropagation();
                });
            });

            // Invite Friend Functionality
            document.addEventListener('DOMContentLoaded', function() {
                const inviteFriendBtn = document.getElementById('inviteFriendBtn');
                const inviteFriendModal = document.getElementById('inviteFriendModal');
                const referralCodeInput = document.getElementById('referralCode');
                const copyReferralBtn = document.getElementById('copyReferralBtn');
                const shareBtns = document.querySelectorAll('.share-btn');
                let userReferralCode = '';

                // Make sure the button is always enabled and shows 'Go'
                if (inviteFriendBtn) {
                    inviteFriendBtn.disabled = false;
                    inviteFriendBtn.textContent = 'Go';
                    inviteFriendBtn.classList.remove('claimed');
                    
                    // Add click handler to show modal
                    inviteFriendBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        if (inviteFriendModal) {
                            inviteFriendModal.style.display = 'block';
                            fetchReferralCode();
                        }
                    });
                }

                // Make sure the button stays as "Go" and doesn't get disabled
                if (inviteFriendBtn) {
                    inviteFriendBtn.classList.remove('claimed');
                    inviteFriendBtn.disabled = false;
                    inviteFriendBtn.innerHTML = 'Go';
                }

                // Fetch user's referral code when modal opens
                if (inviteFriendModal) {
                    inviteFriendModal.addEventListener('show.bs.modal', function () {
                        fetchReferralCode();
                    });
                }

                // Fetch user's referral code
                async function fetchReferralCode() {
                    try {
                        const response = await fetch('php/get_referral_code.php', {
                            method: 'GET',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            credentials: 'same-origin'
                        });

                        if (!response.ok) {
                            throw new Error('Failed to fetch referral code');
                        }

                        const data = await response.json();
                        
                        if (data.success && data.referral_code) {
                            userReferralCode = data.referral_code;
                            if (referralCodeInput) {
                                referralCodeInput.value = userReferralCode;
                            }
                        } else {
                            throw new Error(data.error || 'No referral code found');
                        }
                    } catch (error) {
                        console.error('Error fetching referral code:', error);
                        // Show error in the input
                        if (referralCodeInput) {
                            referralCodeInput.value = 'Error loading code';
                            referralCodeInput.style.color = '#ff6b6b';
                        }
                    }
                }

                // Copy referral code to clipboard
                function copyToClipboard(text) {
                    navigator.clipboard.writeText(text).then(() => {
                        // Visual feedback
                        const originalText = copyReferralBtn.innerHTML;
                        copyReferralBtn.innerHTML = '<i class="fas fa-check"></i> Copied!';
                        copyReferralBtn.classList.add('copied');
                        
                        setTimeout(() => {
                            copyReferralBtn.innerHTML = originalText;
                            copyReferralBtn.classList.remove('copied');
                        }, 2000);
                    }).catch(err => {
                        console.error('Failed to copy:', err);
                        alert('Failed to copy to clipboard. Please try again.');
                    });
                }

                // Handle share button clicks
                function handleShare(platform) {
                    if (!userReferralCode) return;
                    
                    const shareUrl = `${window.location.origin}${window.location.pathname}?ref=${encodeURIComponent(userReferralCode)}`;
                    const shareText = `Join me on Luck Wallet and get 100 $LUCK when you sign up using my referral code: ${userReferralCode}. ${shareUrl}`;
                    
                    switch (platform) {
                        case 'whatsapp':
                            window.open(`https://wa.me/?text=${encodeURIComponent(shareText)}`, '_blank');
                            break;
                        case 'telegram':
                            window.open(`https://t.me/share/url?url=${encodeURIComponent(shareUrl)}&text=${encodeURIComponent(`Join me on Luck Wallet! Use my code: ${userReferralCode} to get 100 $LUCK`)}`, '_blank');
                            break;
                        case 'copy':
                            copyToClipboard(shareUrl);
                            break;
                    }
                }

                // Event Listeners
                if (inviteFriendBtn && inviteFriendModal) {
                    inviteFriendBtn.addEventListener('click', () => {
                        inviteFriendModal.style.display = 'block';
                        if (!userReferralCode) {
                            fetchReferralCode();
                        }
                    });
                }

                if (copyReferralBtn) {
                    copyReferralBtn.addEventListener('click', () => {
                        if (userReferralCode) {
                            copyToClipboard(userReferralCode);
                        }
                    });
                }

                shareBtns.forEach(btn => {
                    btn.addEventListener('click', () => {
                        const platform = btn.dataset.platform;
                        if (platform && userReferralCode) {
                            handleShare(platform);
                        }
                    });
                });
            });

            // --- Transactions/Collection Toggle Logic ---
            const viewToggle = document.getElementById('viewToggle');
            const transactionContent = document.getElementById('transactionContent');
            const collectionContent = document.getElementById('collectionContent');
            const transactionCollectionSection = document.querySelector('.transaction-collection-section');

            // Set initial state
            if (transactionContent && collectionContent) {
                transactionContent.style.display = 'flex';
                collectionContent.style.display = 'none';
                
                // Ensure both containers have the same width
                const transactionWidth = window.getComputedStyle(transactionContent).width;
                collectionContent.style.width = transactionWidth;
            }

            // Toggle between transactions and collection
            if (viewToggle) {
                // Initialize both containers with the same width
                const setEqualWidths = () => {
                    const containerWidth = transactionContent.parentElement.offsetWidth;
                    transactionContent.style.width = containerWidth + 'px';
                    collectionContent.style.width = containerWidth + 'px';
                };
                
                // Set initial widths
                setEqualWidths();
                
                // Update on window resize
                window.addEventListener('resize', setEqualWidths);
                
                viewToggle.addEventListener('change', function() {
                    if (this.checked) {
                        // Show collection, hide transactions
                        transactionContent.style.display = 'none';
                        collectionContent.style.display = 'flex';
                        // Load NFTs when collection tab is shown
                        loadNFTCollection();
                        collectionContent.style.display = 'flex';
                    } else {
                        // Show transactions, hide collection
                        transactionContent.style.display = 'flex';
                        collectionContent.style.display = 'none';
                    }
                    
                    // Ensure both containers maintain the same width after toggle
                    setEqualWidths();
                });
            }

            // Initial state based on checkbox checked status (default to Transactions)
            viewToggle.checked = false; // Ensure Transactions is active by default
            transactionContent.style.display = 'flex';
            collectionContent.style.display = 'none';

            // Single view toggle event listener
            viewToggle.addEventListener('change', function() {
                if (this.checked) {
                    console.log('Collection view active');
                    transactionContent.style.display = 'none';
                    collectionContent.style.display = 'flex';
                    // Load NFTs when collection tab is shown
                    loadNFTCollection();
                } else {
                    console.log('Transactions view active');
                    transactionContent.style.display = 'flex';
                    collectionContent.style.display = 'none';
                }
            });

            // --- Task Claiming Logic ---
        document.querySelectorAll('.task-item .claim-button').forEach(button => {
            // Skip the Invite a Friend button as it has its own handler
            if (button.id === 'inviteFriendBtn') {
                return;
            }
            
            button.addEventListener('click', function(event) {
                event.preventDefault();
                const taskItem = this.closest('.task-item');
                const taskText = taskItem ? taskItem.querySelector('p')?.textContent?.trim() : 'task';
                
                if (!this.disabled) {
                    console.log(`Claiming task: ${taskText}`);
                    showMessageBox(`Claimed ${taskText} task!`);

                    // Disable the button and change text
                    this.disabled = true;
                    this.textContent = 'Claimed!';
                    this.style.backgroundColor = '#333333';
                    this.style.borderColor = '#555555';
                    this.style.boxShadow = 'none';
                }
            });
        });

        // --- Invite Friend Modal Logic ---
        const inviteFriendBtn = document.getElementById('inviteFriendBtn');
        const inviteFriendModal = document.getElementById('inviteFriendModal');
        const referralCodeInput = document.getElementById('referralCode');
        const copyReferralBtn = document.getElementById('copyReferralBtn');
        let userReferralCode = '';

        if (inviteFriendBtn && inviteFriendModal) {
            // Handle Invite Friend button click
            inviteFriendBtn.addEventListener('click', async (e) => {
                e.preventDefault();
                e.stopPropagation();
                
                // Show loading state
                if (referralCodeInput) {
                    referralCodeInput.value = 'Loading...';
                }
                
                // Show the modal
                inviteFriendModal.style.display = 'block';
                
                try {
                    // Fetch the user's referral code
                    const response = await fetch('php/get_referral_code.php');
                    const data = await response.json();
                    
                    if (data.success && data.referral_code) {
                        userReferralCode = data.referral_code;
                        if (referralCodeInput) {
                            referralCodeInput.value = userReferralCode;
                        }
                    } else {
                        console.error('Failed to load referral code:', data.message);
                        showMessageBox('Failed to load referral code. Please try again.');
                        inviteFriendModal.style.display = 'none';
                    }
                } catch (error) {
                    console.error('Error fetching referral code:', error);
                    showMessageBox('Error fetching referral code. Please try again.');
                    inviteFriendModal.style.display = 'none';
                }
            });
            
            // Handle copy referral code
            if (copyReferralBtn && referralCodeInput) {
                copyReferralBtn.addEventListener('click', () => {
                    if (!userReferralCode) return;
                    
                    referralCodeInput.select();
                    document.execCommand('copy');
                    
                    // Show copied feedback
                    const originalText = copyReferralBtn.innerHTML;
                    copyReferralBtn.innerHTML = '<i class="fas fa-check"></i> Copied!';
                    setTimeout(() => {
                        copyReferralBtn.innerHTML = originalText;
                    }, 2000);
                });
            }
            
            // Handle share buttons
            document.querySelectorAll('.share-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    if (!userReferralCode) return;
                    
                    const platform = e.currentTarget.dataset.platform;
                    const shareUrl = `${window.location.origin}${window.location.pathname}?ref=${encodeURIComponent(userReferralCode)}`;
                    const shareText = `Join me on Luck Wallet and get 100 $LUCK when you sign up using my referral code: ${userReferralCode}. ${shareUrl}`;
                    
                    switch (platform) {
                        case 'whatsapp':
                            window.open(`https://wa.me/?text=${encodeURIComponent(shareText)}`, '_blank');
                            break;
                        case 'telegram':
                            window.open(`https://t.me/share/url?url=${encodeURIComponent(shareUrl)}&text=${encodeURIComponent(shareText)}`, '_blank');
                            break;
                        case 'copy':
                            navigator.clipboard.writeText(shareUrl).then(() => {
                                showMessageBox('Link copied to clipboard!');
                            });
                            break;
                    }
                });
            });
        }

            // --- Other Transaction Button Event Listeners ---
            const convertButton = document.getElementById('convertButton');
            const linkAccountModal = document.getElementById('linkAccountModal');
            const closeModal = document.querySelector('.close-modal');
            const modalMessage = document.getElementById('modalMessage');

            if (convertButton) {
                convertButton.addEventListener('click', async function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Show loading state
                    const originalText = convertButton.innerHTML;
                    convertButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking...';
                    convertButton.disabled = true;
                    
                    try {
                        // Check if account is linked
                        const response = await fetch('php/check_luckytime_linked.php');
                        const data = await response.json();
                        
                        if (data.success) {
                            if (data.is_linked) {
                                // Account is linked, proceed to convert
                                window.location.href = 'convert.php';
                            } else {
                                // Show modal for non-linked account
                                modalMessage.innerHTML = `
                                    <p>Your Luck Wallet account is not linked to a LuckyTime account.</p>
                                    <p>To use this feature, please create a LuckyTime account using the same email address: <strong>${data.email || 'your email'}</strong></p>
                                    <p>Or contact support if you need assistance.</p>
                                `;
                                linkAccountModal.style.display = 'flex';
                            }
                        } else {
                            showMessageBox(data.message || 'Error checking account status', 'error');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        showMessageBox('An error occurred. Please try again.', 'error');
                    } finally {
                        // Reset button state
                        convertButton.innerHTML = originalText;
                        convertButton.disabled = false;
                    }
                    
                    return false;
                }, true);
            }
            
            // Close modal when clicking the X or outside the modal
            if (closeModal) {
                closeModal.addEventListener('click', () => {
                    linkAccountModal.style.display = 'none';
                });
            }
            
            window.addEventListener('click', (e) => {
                if (e.target === linkAccountModal) {
                    linkAccountModal.style.display = 'none';
                }
            });
        });
    </script>
    
    <!-- Link Account Modal -->
    <div id="linkAccountModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.7); align-items: center; justify-content: center;">
        <div class="modal-content" style="background: #1a1a2e; padding: 2rem; border-radius: 10px; max-width: 500px; width: 90%; position: relative;">
            <span class="close-modal" style="position: absolute; top: 1rem; right: 1.5rem; color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
            <h3 style="color: #00ffff; margin-top: 0;">Account Not Linked</h3>
            <div id="modalMessage" style="margin: 1.5rem 0;">
                <!-- Message will be inserted here -->
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 1.5rem;">
                <button onclick="document.getElementById('linkAccountModal').style.display='none'" style="background: #333; color: white; border: none; padding: 0.5rem 1rem; border-radius: 5px; cursor: pointer;">Close</button>
                <a href="ecasino/index.php?show=signup" style="background: #00ffff; color: #000; text-decoration: none; padding: 0.5rem 1rem; border-radius: 5px; font-weight: bold;">Create LuckyTime Account</a>
            </div>
        </div>
    </div>
</body>
</html>