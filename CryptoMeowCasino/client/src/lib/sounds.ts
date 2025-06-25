
// Sound effects for casino games
export class SoundManager {
  private sounds: { [key: string]: HTMLAudioElement } = {};
  private enabled: boolean = true;

  constructor() {
    this.loadSounds();
  }

  private loadSounds() {
    const soundFiles = {
      // Crash game sounds
      crashRising: 'data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp56hVFApGn+DyvmYYCjuV3+LEdSgGKHyN+dw=', 
      crashWin: 'data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp56hVFApGn+DyvmYYCjuV3+LEdSgGKHyN+dw=',
      crashLose: 'data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp56hVFApGn+DyvmYYCjuV3+LEdSgGKHyN+dw=',
      
      // Dice game sounds
      diceRoll: 'data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp56hVFApGn+DyvmYYCjuV3+LEdSgGKHyN+dw=',
      diceWin: 'data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp56hVFApGn+DyvmYYCjuV3+LEdSgGKHyN+dw=',
      diceLose: 'data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp56hVFApGn+DyvmYYCjuV3+LEdSgGKHyN+dw=',
      
      // Mines game sounds
      mineReveal: 'data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp56hVFApGn+DyvmYYCjuV3+LEdSgGKHyN+dw=',
      mineExplode: 'data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp56hVFApGn+DyvmYYCjuV3+LEdSgGKHyN+dw=',
      mineCashOut: 'data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp56hVFApGn+DyvmYYCjuV3+LEdSgGKHyN+dw=',
      
      // Hi-Lo game sounds
      cardFlip: 'data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp56hVFApGn+DyvmYYCjuV3+LEdSgGKHyN+dw=',
      cardCorrect: 'data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp56hVFApGn+DyvmYYCjuV3+LEdSgGKHyN+dw=',
      cardWrong: 'data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp56hVFApGn+DyvmYYCjuV3+LEdSgGKHyN+dw=',
      
      // Slots game sounds
      slotSpin: 'data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp56hVFApGn+DyvmYYCjuV3+LEdSgGKHyN+dw=',
      slotWin: 'data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp56hVFApGn+DyvmYYCjuV3+LEdSgGKHyN+dw=',
      slotLose: 'data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp56hVFApGn+DyvmYYCjuV3+LEdSgGKHyN+dw=',
      
      // General sounds
      coinDrop: 'data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp56hVFApGn+DyvmYYCjuV3+LEdSgGKHyN+dw=',
      buttonClick: 'data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp56hVFApGn+DyvmYYCjuV3+LEdSgGKHyN+dw=',
      jackpot: 'data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp56hVFApGn+DyvmYYCjuV3+LEdSgGKHyN+dw='
    };

    // Create audio objects with proper error handling
    for (const [key, dataUrl] of Object.entries(soundFiles)) {
      try {
        const audio = new Audio();
        audio.preload = 'auto';
        audio.volume = 0.3;
        
        // Use Web Audio API to generate synthetic sounds
        this.generateSound(key).then(blob => {
          audio.src = URL.createObjectURL(blob);
          this.sounds[key] = audio;
        }).catch(() => {
          // Fallback to data URL if generation fails
          audio.src = dataUrl;
          this.sounds[key] = audio;
        });
      } catch (error) {
        console.warn(`Failed to load sound: ${key}`, error);
      }
    }
  }

  private async generateSound(type: string): Promise<Blob> {
    const audioContext = new (window.AudioContext || (window as any).webkitAudioContext)();
    const sampleRate = audioContext.sampleRate;
    
    let duration = 0.3;
    let frequency = 440;
    let waveType: OscillatorType = 'sine';
    
    // Configure different sounds
    switch (type) {
      case 'crashRising':
        duration = 0.1;
        frequency = 200;
        waveType = 'sawtooth';
        break;
      case 'crashWin':
        duration = 0.8;
        frequency = 523;
        waveType = 'sine';
        break;
      case 'crashLose':
        duration = 1.0;
        frequency = 130;
        waveType = 'square';
        break;
      case 'diceRoll':
        duration = 0.3;
        frequency = 300;
        waveType = 'square';
        break;
      case 'diceWin':
        duration = 0.6;
        frequency = 660;
        waveType = 'sine';
        break;
      case 'diceLose':
        duration = 0.5;
        frequency = 150;
        waveType = 'sawtooth';
        break;
      case 'mineReveal':
        duration = 0.2;
        frequency = 400;
        waveType = 'triangle';
        break;
      case 'mineExplode':
        duration = 0.8;
        frequency = 80;
        waveType = 'sawtooth';
        break;
      case 'mineCashOut':
        duration = 0.7;
        frequency = 800;
        waveType = 'sine';
        break;
      case 'cardFlip':
        duration = 0.1;
        frequency = 600;
        waveType = 'triangle';
        break;
      case 'cardCorrect':
        duration = 0.4;
        frequency = 700;
        waveType = 'sine';
        break;
      case 'cardWrong':
        duration = 0.6;
        frequency = 200;
        waveType = 'square';
        break;
      case 'slotSpin':
        duration = 0.2;
        frequency = 350;
        waveType = 'sawtooth';
        break;
      case 'slotWin':
        duration = 1.0;
        frequency = 880;
        waveType = 'sine';
        break;
      case 'slotLose':
        duration = 0.4;
        frequency = 180;
        waveType = 'triangle';
        break;
      case 'coinDrop':
        duration = 0.3;
        frequency = 1000;
        waveType = 'sine';
        break;
      case 'buttonClick':
        duration = 0.1;
        frequency = 800;
        waveType = 'square';
        break;
      case 'jackpot':
        duration = 2.0;
        frequency = 523;
        waveType = 'sine';
        break;
    }

    const length = Math.floor(sampleRate * duration);
    const arrayBuffer = audioContext.createBuffer(1, length, sampleRate);
    const channelData = arrayBuffer.getChannelData(0);

    for (let i = 0; i < length; i++) {
      const t = i / sampleRate;
      let sample = 0;

      switch (waveType) {
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

      // Apply envelope (fade in/out)
      const envelope = Math.min(t * 10, (duration - t) * 10, 1);
      channelData[i] = sample * envelope * 0.3;
    }

    // Convert to WAV blob
    return this.audioBufferToWav(arrayBuffer);
  }

  private audioBufferToWav(buffer: AudioBuffer): Blob {
    const length = buffer.length;
    const arrayBuffer = new ArrayBuffer(44 + length * 2);
    const view = new DataView(arrayBuffer);
    const channelData = buffer.getChannelData(0);

    // WAV header
    const writeString = (offset: number, string: string) => {
      for (let i = 0; i < string.length; i++) {
        view.setUint8(offset + i, string.charCodeAt(i));
      }
    };

    writeString(0, 'RIFF');
    view.setUint32(4, 36 + length * 2, true);
    writeString(8, 'WAVE');
    writeString(12, 'fmt ');
    view.setUint32(16, 16, true);
    view.setUint16(20, 1, true);
    view.setUint16(22, 1, true);
    view.setUint32(24, buffer.sampleRate, true);
    view.setUint32(28, buffer.sampleRate * 2, true);
    view.setUint16(32, 2, true);
    view.setUint16(34, 16, true);
    writeString(36, 'data');
    view.setUint32(40, length * 2, true);

    // Convert float samples to 16-bit PCM
    let offset = 44;
    for (let i = 0; i < length; i++) {
      const sample = Math.max(-1, Math.min(1, channelData[i]));
      view.setInt16(offset, sample * 0x7FFF, true);
      offset += 2;
    }

    return new Blob([arrayBuffer], { type: 'audio/wav' });
  }

  play(soundName: string, volume: number = 0.3) {
    if (!this.enabled) return;
    
    const sound = this.sounds[soundName];
    if (sound) {
      try {
        sound.volume = Math.min(1, Math.max(0, volume));
        sound.currentTime = 0;
        const playPromise = sound.play();
        if (playPromise) {
          playPromise.catch(error => {
            console.warn(`Sound play failed: ${soundName}`, error);
          });
        }
      } catch (error) {
        console.warn(`Sound play error: ${soundName}`, error);
      }
    }
  }

  setEnabled(enabled: boolean) {
    this.enabled = enabled;
  }

  isEnabled(): boolean {
    return this.enabled;
  }

  setVolume(soundName: string, volume: number) {
    const sound = this.sounds[soundName];
    if (sound) {
      sound.volume = Math.min(1, Math.max(0, volume));
    }
  }
}

// Create singleton instance
export const soundManager = new SoundManager();
