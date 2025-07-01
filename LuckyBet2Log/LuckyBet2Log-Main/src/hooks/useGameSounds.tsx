
import { useCallback, useState, useEffect, useRef } from 'react';

interface GameSounds {
  playDiamondSound: () => void;
  playExplosionSound: () => void;
  playWinSound: () => void;
  playLossSound: () => void;
  playJackpotSound: () => void;
  playWheelSpinSound: () => void;
  playWheelStopSound: () => void;
  playCardDealSound: () => void;
  audioEnabled: boolean;
  enableAudio: () => void;
}

export const useGameSounds = (): GameSounds => {
  const [audioEnabled, setAudioEnabled] = useState(false);
  const [userInteracted, setUserInteracted] = useState(false);
  const audioContextRef = useRef<AudioContext | null>(null);
  const soundBuffersRef = useRef<Map<string, AudioBuffer>>(new Map());

  // Initialize audio context once
  useEffect(() => {
    const initAudioContext = () => {
      if (!audioContextRef.current && (window.AudioContext || (window as any).webkitAudioContext)) {
        audioContextRef.current = new (window.AudioContext || (window as any).webkitAudioContext)();
        console.log('Audio context initialized');
      }
    };

    const handleUserInteraction = () => {
      setUserInteracted(true);
      initAudioContext();
      console.log('User interaction detected - audio ready');
    };

    // Add event listeners for user interaction
    document.addEventListener('click', handleUserInteraction, { once: true });
    document.addEventListener('keydown', handleUserInteraction, { once: true });
    document.addEventListener('touchstart', handleUserInteraction, { once: true });

    return () => {
      document.removeEventListener('click', handleUserInteraction);
      document.removeEventListener('keydown', handleUserInteraction);
      document.removeEventListener('touchstart', handleUserInteraction);
    };
  }, []);

  const enableAudio = useCallback(() => {
    setAudioEnabled(true);
    if (!audioContextRef.current && (window.AudioContext || (window as any).webkitAudioContext)) {
      audioContextRef.current = new (window.AudioContext || (window as any).webkitAudioContext)();
    }
    console.log('Audio manually enabled by user');
  }, []);

  // Create audio buffer for a specific sound
  const createAudioBuffer = useCallback((frequency: number, duration: number, type: OscillatorType = 'sine'): AudioBuffer | null => {
    if (!audioContextRef.current) return null;

    try {
      const sampleRate = audioContextRef.current.sampleRate;
      const numSamples = Math.floor(sampleRate * duration);
      const buffer = audioContextRef.current.createBuffer(1, numSamples, sampleRate);
      const channelData = buffer.getChannelData(0);

      for (let i = 0; i < numSamples; i++) {
        const t = i / sampleRate;
        let sample = 0;

        switch (type) {
          case 'sine':
            sample = Math.sin(2 * Math.PI * frequency * t);
            break;
          case 'square':
            sample = Math.sin(2 * Math.PI * frequency * t) > 0 ? 1 : -1;
            break;
          case 'sawtooth':
            sample = 2 * (frequency * t - Math.floor(frequency * t + 0.5));
            break;
          case 'triangle':
            sample = 2 * Math.abs(2 * (frequency * t - Math.floor(frequency * t + 0.5))) - 1;
            break;
        }

        // Apply envelope (fade in/out) for smoother sound
        const fadeTime = Math.min(0.01, duration * 0.1);
        const fadeInSamples = Math.floor(fadeTime * sampleRate);
        const fadeOutSamples = Math.floor(fadeTime * sampleRate);

        if (i < fadeInSamples) {
          sample *= i / fadeInSamples;
        } else if (i > numSamples - fadeOutSamples) {
          sample *= (numSamples - i) / fadeOutSamples;
        }

        channelData[i] = sample * 0.3; // Lower base volume
      }

      return buffer;
    } catch (error) {
      console.error('Error creating audio buffer:', error);
      return null;
    }
  }, []);

  // Play audio buffer
  const playBuffer = useCallback((buffer: AudioBuffer | null, volume: number = 0.5) => {
    if (!buffer || !audioContextRef.current || !audioEnabled || !userInteracted) return;

    try {
      const source = audioContextRef.current.createBufferSource();
      const gainNode = audioContextRef.current.createGain();

      source.buffer = buffer;
      source.connect(gainNode);
      gainNode.connect(audioContextRef.current.destination);

      gainNode.gain.setValueAtTime(volume, audioContextRef.current.currentTime);
      source.start(0);

      // Clean up after playback
      source.onended = () => {
        source.disconnect();
        gainNode.disconnect();
      };
    } catch (error) {
      console.error('Error playing audio buffer:', error);
    }
  }, [audioEnabled, userInteracted]);

  // Pre-generate sound buffers
  const generateSoundBuffers = useCallback(() => {
    if (!audioContextRef.current) return;

    const sounds = {
      diamond: { freq: 880, duration: 0.2, type: 'sine' as OscillatorType },
      explosion: { freq: 150, duration: 0.4, type: 'sawtooth' as OscillatorType },
      win1: { freq: 523, duration: 0.15, type: 'sine' as OscillatorType },
      win2: { freq: 659, duration: 0.15, type: 'sine' as OscillatorType },
      win3: { freq: 784, duration: 0.2, type: 'sine' as OscillatorType },
      loss1: { freq: 300, duration: 0.2, type: 'triangle' as OscillatorType },
      loss2: { freq: 250, duration: 0.2, type: 'triangle' as OscillatorType },
      loss3: { freq: 200, duration: 0.3, type: 'triangle' as OscillatorType },
      jackpot1: { freq: 523, duration: 0.25, type: 'sine' as OscillatorType },
      jackpot2: { freq: 659, duration: 0.25, type: 'sine' as OscillatorType },
      jackpot3: { freq: 784, duration: 0.25, type: 'sine' as OscillatorType },
      jackpot4: { freq: 1047, duration: 0.3, type: 'sine' as OscillatorType },
      wheelSpin: { freq: 220, duration: 0.1, type: 'square' as OscillatorType },
      wheelStop: { freq: 400, duration: 0.15, type: 'triangle' as OscillatorType },
      cardDeal: { freq: 600, duration: 0.1, type: 'sine' as OscillatorType }
    };

    Object.entries(sounds).forEach(([name, config]) => {
      const buffer = createAudioBuffer(config.freq, config.duration, config.type);
      if (buffer) {
        soundBuffersRef.current.set(name, buffer);
      }
    });

    console.log('Sound buffers generated:', soundBuffersRef.current.size);
  }, [createAudioBuffer]);

  // Generate buffers when audio is enabled
  useEffect(() => {
    if (audioEnabled && userInteracted && audioContextRef.current) {
      generateSoundBuffers();
    }
  }, [audioEnabled, userInteracted, generateSoundBuffers]);

  const playDiamondSound = useCallback(() => {
    if (!audioEnabled || !userInteracted) return;
    console.log('Playing diamond sound');
    const buffer = soundBuffersRef.current.get('diamond');
    playBuffer(buffer, 0.6);
  }, [audioEnabled, userInteracted, playBuffer]);

  const playExplosionSound = useCallback(() => {
    if (!audioEnabled || !userInteracted) return;
    console.log('Playing explosion sound');
    const buffer = soundBuffersRef.current.get('explosion');
    playBuffer(buffer, 0.8);
  }, [audioEnabled, userInteracted, playBuffer]);

  const playWinSound = useCallback(() => {
    if (!audioEnabled || !userInteracted) return;
    console.log('Playing win sound');
    
    // Play ascending melody
    const buffers = ['win1', 'win2', 'win3'];
    buffers.forEach((name, index) => {
      setTimeout(() => {
        const buffer = soundBuffersRef.current.get(name);
        playBuffer(buffer, 0.7);
      }, index * 150);
    });
  }, [audioEnabled, userInteracted, playBuffer]);

  const playLossSound = useCallback(() => {
    if (!audioEnabled || !userInteracted) return;
    console.log('Playing loss sound');
    
    // Play descending melody
    const buffers = ['loss1', 'loss2', 'loss3'];
    buffers.forEach((name, index) => {
      setTimeout(() => {
        const buffer = soundBuffersRef.current.get(name);
        playBuffer(buffer, 0.5);
      }, index * 200);
    });
  }, [audioEnabled, userInteracted, playBuffer]);

  const playJackpotSound = useCallback(() => {
    if (!audioEnabled || !userInteracted) return;
    console.log('Playing jackpot sound');
    
    // Play celebratory ascending melody
    const buffers = ['jackpot1', 'jackpot2', 'jackpot3', 'jackpot4'];
    buffers.forEach((name, index) => {
      setTimeout(() => {
        const buffer = soundBuffersRef.current.get(name);
        playBuffer(buffer, 0.8);
      }, index * 120);
    });

    // Add sparkle effects
    setTimeout(() => {
      for (let i = 0; i < 5; i++) {
        setTimeout(() => {
          const buffer = soundBuffersRef.current.get('diamond');
          playBuffer(buffer, 0.4);
        }, i * 100);
      }
    }, 500);
  }, [audioEnabled, userInteracted, playBuffer]);

  const playWheelSpinSound = useCallback(() => {
    if (!audioEnabled || !userInteracted) return;
    console.log('Playing wheel spin sound');
    
    // Create spinning effect with multiple quick sounds
    const playSpinTick = () => {
      const buffer = soundBuffersRef.current.get('wheelSpin');
      playBuffer(buffer, 0.3);
    };

    // Play multiple ticks to simulate spinning
    for (let i = 0; i < 20; i++) {
      setTimeout(playSpinTick, i * 200);
    }
  }, [audioEnabled, userInteracted, playBuffer]);

  const playWheelStopSound = useCallback(() => {
    if (!audioEnabled || !userInteracted) return;
    console.log('Playing wheel stop sound');
    const buffer = soundBuffersRef.current.get('wheelStop');
    playBuffer(buffer, 0.6);
  }, [audioEnabled, userInteracted, playBuffer]);

  const playCardDealSound = useCallback(() => {
    if (!audioEnabled || !userInteracted) return;
    console.log('Playing card deal sound');
    const buffer = soundBuffersRef.current.get('cardDeal');
    playBuffer(buffer, 0.4);
  }, [audioEnabled, userInteracted, playBuffer]);

  return {
    playDiamondSound,
    playExplosionSound,
    playWinSound,
    playLossSound,
    playJackpotSound,
    playWheelSpinSound,
    playWheelStopSound,
    playCardDealSound,
    audioEnabled: audioEnabled && userInteracted,
    enableAudio
  };
};
