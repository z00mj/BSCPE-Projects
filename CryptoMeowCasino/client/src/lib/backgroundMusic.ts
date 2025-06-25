
export class BackgroundMusicManager {
  private audio: HTMLAudioElement | null = null;
  private enabled: boolean = true;
  private volume: number = 0.5;
  private isInitialized: boolean = false;

  constructor() {
    this.initializeAudio();
  }

  private initializeAudio() {
    if (this.isInitialized) return;
    
    try {
      this.audio = new Audio();
      this.audio.src = "/sounds/bgm.mp3";
      this.audio.loop = true;
      this.audio.volume = this.volume;
      this.audio.preload = 'auto';
      this.isInitialized = true;
      
      // Handle audio loading errors
      this.audio.addEventListener('error', (e) => {
        console.warn('Background music failed to load:', e);
      });
      
      // Prevent the audio from stopping due to browser policies
      this.audio.addEventListener('ended', () => {
        if (this.enabled) {
          this.play();
        }
      });
      
    } catch (error) {
      console.warn('Failed to initialize background music:', error);
    }
  }

  async play() {
    if (!this.audio || !this.enabled) return;
    
    try {
      // Reset to beginning if needed, but don't interrupt ongoing playback
      if (this.audio.paused) {
        await this.audio.play();
      }
    } catch (error) {
      console.warn('Failed to play background music:', error);
    }
  }

  pause() {
    if (this.audio && !this.audio.paused) {
      this.audio.pause();
    }
  }

  setVolume(volume: number) {
    this.volume = Math.min(1, Math.max(0, volume));
    if (this.audio) {
      this.audio.volume = this.volume;
    }
  }

  getVolume(): number {
    return this.volume;
  }

  setEnabled(enabled: boolean) {
    this.enabled = enabled;
    if (enabled) {
      this.play();
    } else {
      this.pause();
    }
  }

  isEnabled(): boolean {
    return this.enabled;
  }

  isPlaying(): boolean {
    return this.audio ? !this.audio.paused : false;
  }
}

// Create singleton instance
export const backgroundMusicManager = new BackgroundMusicManager();
