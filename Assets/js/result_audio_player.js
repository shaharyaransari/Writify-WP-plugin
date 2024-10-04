window.loadedAudio = null;
document.addEventListener("DOMContentLoaded", () => {
    const player = document.querySelector('.isfp-audio-player');
    const playToggle = player.querySelector('.isfp-play-toggle');
    const volumeRange = player.querySelector('.volume input[type=range]');
    const barHoverBox = player.querySelector('.volume .bar-hoverbox');
    const barFill = player.querySelector('.volume .bar .bar-fill');
    const audioElement = player.querySelector('audio');
    const volumeIcon = player.querySelector('.isfp-volumn-handler i');
    const seekBar = document.getElementById('seek-bar');
    const seekThumb = document.getElementById('seek-thumb');
    const seekProgress = document.getElementById('seek-progress');
    
    let isBarActive = false;
    let audioDuration = 0;
    let dragging = false;


    // Run some functions when audio is played
    audioElement.addEventListener('play', function() {
        // console.log('Audio is playing');
        playToggle.dataset.playing = true;
    });

    // Run some functions when audio is paused
    audioElement.addEventListener('pause', function() {
        // console.log('Audio is paused');
        playToggle.dataset.playing = false;
    });
    // Load audio dynamically and enable the player
    function loadAudio(url) {
        return new Promise((resolve, reject) => {
            if (url) {
                audioElement.src = url;
                window.loadedAudio = url;
                updatePagination(url);
                audioElement.load();
                enablePlayer();  // Enable the player when audio is loaded
    
                // Get audio duration when metadata is loaded
                audioElement.addEventListener('loadedmetadata', () => {
                    audioDuration = audioElement.duration;
                    if (isNaN(audioElement.duration) || audioElement.duration === Infinity) {
                        audioElement.currentTime = 1e101; // Seek to an extreme value to force duration calculation
                    }
                    // console.log('Audio duration:', audioDuration, 'seconds');
                    resolve(true); // Audio successfully loaded
                });
    
                // Handle error in loading the audio
                audioElement.addEventListener('error', (e) => {
                    console.error('Error loading audio:', e);
                    reject(false); // Audio failed to load
                });
    
                // Update seek bar while playing
                audioElement.addEventListener('timeupdate', () => {
                    if (!dragging) {
                        if(audioDuration === 0){
                            audioDuration = 1;
                        }
                        const progress = (audioElement.currentTime / audioDuration) * 100;
                        
                        updateSeekBar(progress);
                    }
                });
    
            } else {
                disablePlayer();  // Disable the player if no URL
                reject(false); // No audio URL provided
            }
        });
    }

    // Play/Pause functionality
    playToggle.addEventListener('click', function () {
        if (!audioElement.src) return; // Prevent play action if no audio loaded

        const isPlaying = playToggle.dataset.playing === "true";
        playToggle.dataset.playing = isPlaying ? "false" : "true";
        isPlaying ? audioElement.pause() : audioElement.play();
    });

    // Play audio segment from start to end seconds
    function playSegment(start, end) {
        if (!audioElement.src) return; // Prevent playing segment if no audio loaded
        audioElement.currentTime = start;
        audioElement.play();

        const interval = setInterval(() => {
            if (audioElement.currentTime >= end || audioElement.paused) {
                audioElement.pause();
                clearInterval(interval);
            }
        }, 100);
    }

    // Forward the audio by a custom number of seconds
    function forwardAudio(seconds) {
        if (!audioElement.src) return; // Prevent forward action if no audio loaded
        const newTime = audioElement.currentTime + seconds;
        if (newTime < audioElement.duration) {
            audioElement.currentTime = newTime;
        } else {
            audioElement.currentTime = audioElement.duration; // Go to the end if forward exceeds duration
        }
    }

    window.forwardAudio = forwardAudio;

    // Backward the audio by a custom number of seconds
    function backwardAudio(seconds) {
        if (!audioElement.src) return; // Prevent backward action if no audio loaded
        const newTime = audioElement.currentTime - seconds;
        if (newTime > 0) {
            audioElement.currentTime = newTime;
        } else {
            audioElement.currentTime = 0; // Go to the beginning if backward exceeds 0
        }
    }
    window.backwardAudio = backwardAudio;
    

    // Update volume based on input range
    const updateVolume = (value) => {
        barFill.style.width = value + "%";
        volumeRange.setAttribute("value", value);
        if(value < 1){
            volumeIcon.classList.remove('fa-volume-down')
            volumeIcon.classList.remove('fa-volume-up')
            volumeIcon.classList.add('fa-volume-off')
        }else if(value <= 30){
            volumeIcon.classList.add('fa-volume-down')
            volumeIcon.classList.remove('fa-volume-up')
            volumeIcon.classList.remove('fa-volume-up')
            volumeIcon.classList.remove('fa-volume-off')
        }else{
            volumeIcon.classList.remove('fa-volume-down')
            volumeIcon.classList.add('fa-volume-up')
            volumeIcon.classList.remove('fa-volume-off')
        }
        audioElement.volume = value / 100; // Update the actual volume
    };

    // Set default volume and attach events for volume control
    updateVolume(volumeRange.value);

    // Calculate volume based on hover position
    const calculateFill = (e) => {
        let offsetX = e.offsetX;
        if (e.type === "touchmove") {
            offsetX = e.touches[0].pageX - e.touches[0].target.offsetLeft;
        }

        const width = e.target.offsetWidth - 30;
        updateVolume(Math.max(Math.min((offsetX - 15) / width * 100.0, 100.0), 0));
    };

    barHoverBox.addEventListener("touchstart", (e) => { isBarActive = true; calculateFill(e); }, true);
    barHoverBox.addEventListener("touchmove", (e) => { if (isBarActive) calculateFill(e); }, true);
    barHoverBox.addEventListener("mousedown", (e) => { isBarActive = true; calculateFill(e); }, true);
    barHoverBox.addEventListener("mousemove", (e) => { if (isBarActive) calculateFill(e); });

    // Add mouse/touch end events to stop dragging
    document.addEventListener("mouseup", () => { isBarActive = false; }, true);
    document.addEventListener("touchend", () => { isBarActive = false; }, true);

    // Disable the player and apply faded styles
    function disablePlayer() {
        playToggle.setAttribute('disabled', true);
        volumeRange.setAttribute('disabled', true);
        player.classList.add('disabled');
    }

    // Enable the player and remove faded styles
    function enablePlayer() {
        playToggle.removeAttribute('disabled');
        volumeRange.removeAttribute('disabled');
        player.classList.remove('disabled');
    }

    // Initially disable the player
    disablePlayer();

    // Expose functions globally for use
    window.loadAudio = loadAudio;
    window.playSegment = playSegment;

    // Format time to MM:SS
    function formatTime(seconds) {
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = Math.floor(seconds % 60);
        return `${minutes}:${remainingSeconds < 10 ? '0' : ''}${remainingSeconds}`;
    }

    // Update seek thumb content and position
    function updateSeekBar(value) {
        const progress = Math.max(0, Math.min(value, 100));
        seekProgress.style.width = progress + "%";
        const currentTime = (progress / 100) * audioDuration;
        seekThumb.style.left = progress + "%";
        seekThumb.innerHTML = formatTime(currentTime);
    }

    // Mouse down on seek bar or seek thumb for direct seeking or dragging
    function startDragging(e) {
        dragging = true;
        updateThumbPosition(e);

        document.addEventListener('mousemove', updateThumbPosition);
        document.addEventListener('mouseup', stopDragging);

        e.preventDefault();  // Prevent default browser behavior
    }

    // Update thumb and progress bar position
    function updateThumbPosition(e) {
        const rect = seekBar.getBoundingClientRect();
        const seekPosition = (e.clientX - rect.left) / rect.width;
        const progress = Math.max(0, Math.min(seekPosition * 100, 100)); // Ensure value stays between 0 and 100
        updateSeekBar(progress);
    }

    // Stop dragging the seek thumb
    function stopDragging(e) {
        dragging = false;
        document.removeEventListener('mousemove', updateThumbPosition);
        document.removeEventListener('mouseup', stopDragging);

        const rect = seekBar.getBoundingClientRect();
        const seekPosition = (e.clientX - rect.left) / rect.width;
        const progress = Math.max(0, Math.min(seekPosition * 100, 100));
        audioElement.currentTime = (progress / 100) * audioDuration;

        e.preventDefault();  // Prevent default behavior
    }

    // Attach mouse events to the thumb and seek bar
    seekThumb.addEventListener('mousedown', startDragging);
    seekBar.addEventListener('mousedown', startDragging);


});