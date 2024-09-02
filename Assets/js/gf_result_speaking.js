window.onload = function() {
    jQuery(document).ready(function($) {
            // Sync Two Result Page Sliders 
            const transcriptCarousel = document.querySelector('#result-transcript-carousel .swiper').swiper;
            const resultDataCarousel = document.querySelector('#result-data-carousel .swiper').swiper;

            transcriptCarousel.allowTouchMove = false;
            transcriptCarousel.on('slideChange', function() {
                resultDataCarousel.slideTo(transcriptCarousel.activeIndex);
            });

            resultDataCarousel.on('slideChange', function() {
                transcriptCarousel.slideTo(resultDataCarousel.activeIndex);
            });


            // Logic For Streaming Response And Switching Slides
            let slideActiveIndex = 1; // 1 For Vocab, 2 For Grammer, 3 For Pronun, 4 For Fluency
            let lastEventType = 'message'; // Defaults to message Event
            let slidesCount = 4; // Number of Slides
            let userInteracted = false; // A Flag to Check if User Interacted or Not with Slides
            let loadingSavedData = false;
           
            let audioFiles = null; //Variable to Hold File Paths
            let whisperResponse = []; // From Whisper [Array of Responses]
            let vocabSuggestions = ''; // From Chat Completions
            let grammerSuggestions = null; // From Language Tool
            let pronunciationResponse = []; // From Pronunciation API

            const vocabTranscriptWrap = $('#result-transcript-carousel .swiper #vocab-transcript-wrap .elementor-widget-container');
            const grammerTranscriptWrap = $('#result-transcript-carousel .swiper #grammer-transcript-wrap .elementor-widget-container');
            const pronunTranscriptWrap = $('#result-transcript-carousel .swiper #pronun-transcript-wrap .elementor-widget-container');
            const fluencyTranscriptWrap = $('#result-transcript-carousel .swiper #fluency-transcript-wrap .elementor-widget-container');


            var div_index = 0, div_index_str = '';
            var buffer = ""; // Buffer for holding messages
            var responseBuffer = '';
            var md = new Remarkable();

            const formId = gfResultSpeaking.formId;
            const entryId = gfResultSpeaking.entryId;
            const nonce = gfResultSpeaking.nonce;
            const sourceUrl = `${gfResultSpeaking.restUrl}writify/v1/event_stream_openai?form_id=${formId}&entry_id=${entryId}&_wpnonce=${nonce}`;
            const source = new EventSource(sourceUrl);
            source.addEventListener('message', handleEventStream);
            source.addEventListener('whisper', handleEventStream);
            source.addEventListener('chat/completions', handleEventStream);
            source.addEventListener('languagetool', handleEventStream);
            source.addEventListener('pronunciation', handleEventStream);
            source.addEventListener('audio_urls', handleEventStream);

            function handleEventStream(event) {
                if (event.data == "[ALLDONE]") {
                    resultDataCarousel.slideTo(1); // Reverse Back to First Slide
                    source.close();
                }else if(event.data == "[FIRST-TIME]") {
                    loadingSavedData = true;
                } else if (event.data.startsWith("[DIVINDEX-")) {
                    // New Feed Started

                } else if (event.data == "[DONE]") {
                    // Previous Feed Completed

                    
                } else if(event.type !== 'message') {
                    // Event Contains Data to Process
                    
                    // We Will Always get the Audio Files In Begining of Stream
                    if(event.type == 'audio_urls'){
                        audioFiles = JSON.parse(event.data); // Array of Audio File Paths
                        audioFiles = audioFiles.response;
                    }

                    else if(event.type == 'whisper'){ // Whisper Streaming Logic

                        // Auto Slide Logic 
                        if(!userInteracted && slideActiveIndex !== 1){
                            // Swipe to First Slide 
                            console.log('Slide To Vocab');
                            // resultDataCarousel.slideTo(1);
                        }

                        if(whisperResponse.length < 1){ // First Paint
                            vocabTranscriptWrap.empty(); // Empty the Wrapper
                        }
                        // Store The Transcript Data From Whisper
                        let currentWhisperResponse = JSON.parse(event.data);
                        whisperResponse.push(currentWhisperResponse);
                        // Extract Text with TimeStamp that should be Clickable to Play Audio
                        let fileIndex = whisperResponse.length - 1;
                        console.log(audioFiles);
                        // Also Stream The Data on Frontend Live
                        let fileName = extractHumanReadableFileName(audioFiles[fileIndex]);
                        let formatedResponse = formatWhisperResponse(currentWhisperResponse);
                        console.log(formatedResponse);
                        
                        vocabTranscriptWrap.append(`<strong>File: ${fileName}</strong>:`);
                        vocabTranscriptWrap.append(formatedResponse);
                    }
                    else if(event.type == 'chat/completions'){ // Vocabulary Suggestions Streaming Logic
                        // Auto Slide Logic 
                        if(!userInteracted && slideActiveIndex !== 1 && loadingSavedData){
                            // Swipe to First Slide 
                            console.log('Slide To Vocab');
                            resultDataCarousel.slideTo(1);
                        }
                        // There Could be chat/completions events we need to store data in One For All
                        // Also Stream The Data on Frontend Live
                        // Create Transcript Markup On Successfull Completion of All Events
                    }
                    else if(event.type == 'languagetool'){ // Grammer Suggestions Streaming Logic
                        // Auto Slide Logic 
                        if(!userInteracted && slideActiveIndex !== 2 && loadingSavedData){
                            // 'Slide To Grammer
                            console.log('Slide To Grammer');
                            resultDataCarousel.slideTo(2);
                        }
                        // There Could be chat/completions events we need to store data in One For All
                        // Also Stream The Data on Frontend After Each Successfull Response.
                        // Create Transcript Markup On Successfull Completion of All Events
                    }
                    else if(event.type == 'pronunciation'){ // pronunciation Streaming Logic
                        // Auto Slide Logic 
                        if(!userInteracted && slideActiveIndex !== 3 && loadingSavedData){
                            // Slide To Pronunciation
                            console.log('Slide To Pronunciation');
                            resultDataCarousel.slideTo(3);
                        }
                        // Prepare Transcript With Errors Highlighted For Pronunciation
                        // Prepare Transcript With Errors Highlighted For Fluency
                        // Keep Showing The Skeleton Loading Until At least One Audio File is Processed

                    }
                    
                }
            }

            source.onerror = function (event) {
                div_index = 0;
                source.close();
                jQuery('.error_message').css('display', 'flex');
            };


            // Utility Functions 
            function formatWhisperResponse(response) {
                var formattedHtml = '';
                response = response.response; // Actual Response is Inside a Response Wrapper
            
                if (response && response.segments) {
                    response.segments.forEach(function(segment) {
                        var startTime = segment.start;
                        var formattedTime = formatTime(startTime);
            
                        // Create a clickable span for the time
                        var timeSpan = $(`<span onclick="alert('OK')">`)
                            .text(`[${formattedTime}]`)
                            .css({
                                'color': 'blue',
                                'cursor': 'pointer',
                                'text-decoration': 'underline'
                            });
            
                        // Create the text element
                        var textElement = $('<span>').text(segment.text);
            
                        // Create a div to hold the time and text, each segment in a new line
                        var segmentDiv = $('<div>')
                            .append(timeSpan)
                            .append(' ')
                            .append(textElement);
            
                        // Append the segment div to the formattedHtml
                        formattedHtml += segmentDiv.prop('outerHTML') + '<br>';
                    });
                }
            
                // Return the formatted HTML
                return formattedHtml;
            }
            
            // Helper function to format time in MM:SS format
            function formatTime(seconds) {
                var minutes = Math.floor(seconds / 60);
                var remainingSeconds = Math.floor(seconds % 60);
                return minutes + ':' + (remainingSeconds < 10 ? '0' : '') + remainingSeconds;
            }

            function extractHumanReadableFileName(url) {
                // Step 1: Extract the file name from the URL
                var fileName = url.split('/').pop().split('#')[0].split('?')[0];
            
                // Step 2: Remove the file extension (optional)
                var nameWithoutExtension = fileName.substring(0, fileName.lastIndexOf('.')) || fileName;
            
                // Step 3: Convert to human-readable format
                var humanReadableName = nameWithoutExtension
                    .replace(/[-_]/g, ' ')          // Replace hyphens and underscores with spaces
                    .replace(/\b\w/g, function(l) { // Capitalize the first letter of each word
                        return l.toUpperCase();
                    });
            
                return humanReadableName;
            }
    });
};