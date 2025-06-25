
class BackgroundMusicManager {
  private audio: HTMLAudioElement | null = null;
  private enabled: boolean = true;
  private volume: number = 0.5;
  private isInitialized: boolean = false;
  private currentTrackIndex: number = 0;
  private availableTracks: string[] = [];
  private currentTrackName: string = '';
  private isLoadingNextTrack: boolean = false;

  constructor() {
    this.initializeAudio();
  }

  private async detectAvailableTracks(): Promise<string[]> {
    const tracks: string[] = [];
    
    // Check for bgm.mp3
    try {
      const response = await fetch('/sounds/bgm.mp3', { method: 'HEAD' });
      if (response.ok) {
        tracks.push('bgm.mp3');
      }
    } catch (error) {
      console.log('bgm.mp3 not found');
    }

    // Check for bgm1.mp3
    try {
      const response = await fetch('/sounds/bgm1.mp3', { method: 'HEAD' });
      if (response.ok) {
        tracks.push('bgm1.mp3');
      }
    } catch (error) {
      console.log('bgm1.mp3 not found');
    }

    console.log('Available music tracks:', tracks);
    return tracks;
  }

  private async initializeAudio() {
    if (this.isInitialized) return;
    
    try {
      // Detect available tracks first
      this.availableTracks = await this.detectAvailableTracks();
      
      if (this.availableTracks.length === 0) {
        console.warn('No background music tracks found');
        return;
      }

      this.audio = new Audio();
      this.loadCurrentTrack();
      this.audio.volume = this.volume;
      this.audio.preload = 'auto';
      this.audio.loop = false; // We handle looping manually to cycle between tracks
      
      // Handle track ending - play next track
      this.audio.addEventListener('ended', () => {
        console.log('Track ended, playing next track');
        this.playNextTrack();
      });
      
      // Handle audio loading errors
      this.audio.addEventListener('error', (e) => {
        console.warn('Background music failed to load:', e);
        // Only try next track if we have multiple tracks and haven't tried recently
        if (!this.isLoadingNextTrack && this.availableTracks.length > 1) {
          this.playNextTrack();
        }
      });

      // Handle successful load
      this.audio.addEventListener('canplay', () => {
        console.log(`Track loaded successfully: ${this.currentTrackName}`);
        this.isLoadingNextTrack = false;
      });
      
      this.isInitialized = true;
      console.log('Background music manager initialized');
    } catch (error) {
      console.warn('Failed to initialize background music:', error);
    }
  }

  private loadCurrentTrack() {
    if (!this.audio || this.availableTracks.length === 0) return;

    const trackName = this.availableTracks[this.currentTrackIndex];
    this.audio.src = `/sounds/${trackName}`;
    this.currentTrackName = trackName;
    console.log(`Loading track: ${trackName} (${this.currentTrackIndex + 1}/${this.availableTracks.length})`);
  }

  private playNextTrack() {
    if (this.availableTracks.length === 0 || this.isLoadingNextTrack) return;

    this.isLoadingNextTrack = true;
    
    // Move to next track, loop back to first if at end
    this.currentTrackIndex = (this.currentTrackIndex + 1) % this.availableTracks.length;
    console.log(`Switching to track ${this.currentTrackIndex + 1}/${this.availableTracks.length}`);
    
    this.loadCurrentTrack();
    
    if (this.enabled) {
      // Small delay to ensure the track is loaded
      setTimeout(() => {
        this.play();
      }, 100);
    }
  }

  async play() {
    if (!this.audio || !this.enabled) return;
    
    try {
      if (this.audio.paused) {
        console.log(`Playing: ${this.currentTrackName}`);
        await this.audio.play();
      }
    } catch (error) {
      console.warn('Failed to play background music:', error);
      // Only try next track if we have multiple tracks and haven't tried recently
      if (!this.isLoadingNextTrack && this.availableTracks.length > 1) {
        this.playNextTrack();
      }
    }
  }

  pause() {
    if (this.audio && !this.audio.paused) {
      this.audio.pause();
      console.log('Background music paused');
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
      console.log('Background music enabled');
      // Start playing immediately when enabled
      if (this.audio && this.audio.paused) {
        this.play();
      }
    } else {
      console.log('Background music disabled');
      this.pause();
    }
  }

  isEnabled(): boolean {
    return this.enabled;
  }

  isPlaying(): boolean {
    return this.audio ? !this.audio.paused : false;
  }

  getCurrentTrackName(): string {
    return this.currentTrackName;
  }

  getAvailableTracksCount(): number {
    return this.availableTracks.length;
  }

  // Manual track switching
  nextTrack() {
    if (this.availableTracks.length <= 1) return;
    
    this.pause();
    this.playNextTrack();
  }

  // Reset to first track
  reset() {
    this.currentTrackIndex = 0;
    this.loadCurrentTrack();
    if (this.enabled) {
      this.play();
    }
  }
}

// Create singleton instance
export const backgroundMusicManager = new BackgroundMusicManager();
