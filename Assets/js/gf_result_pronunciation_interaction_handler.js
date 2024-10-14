/**
 * Script Name: Pronunciation Interaction Handler
 * Version: 1.0.0
 * Last Updated: 13-11-2023
 * Author: bi1101
 * Description: Turn static output from languagetool into intereactive clickable div.
 */

// Cache frequently used selectors
const $pronunDocument = jQuery(document);
const $pronuntranscriptWrap = jQuery('#pronun-transcript-wrap');
const $pronunErrorsWrap = jQuery('#pronun-suggestions');
// Adding Fluency
const $fluencytranscriptWrap = jQuery('#fluency-transcript-wrap');

let pronunErrorCounter = 0;
let pronunciationMouseX = 0;
let pronunciationMouseY = 0;

// Update mouseX and mouseY whenever the user moves the mouse
jQuery(document).on('mousemove', function(event) {
    pronunciationMouseX = event.pageX;
    pronunciationMouseY = event.pageY;
});

/**
 * Process and handle pronunciation data response.
 * @param {string} pronunciationData - JSON response from the Event Stream Endpoint.
 * @param {number} fileIndex - Index of the file being processed.
 */
function processPronunciationData(pronunciationData,fileIndex){
    let response = JSON.parse(pronunciationData).response;

    let userAllowedToUseAPI = false;
    if(response.hasOwnProperty('error') && response.error){
        userAllowedToUseAPI = false
        removePronunLoader("Auto Pronunciation Checker limit Reached. Click on the mispronounced words manually to generate feedback. Double click to uncheck.");
    }else{
        userAllowedToUseAPI = true
        removePronunLoader();
    }
    if(userAllowedToUseAPI){
        let fileBlocks = $pronuntranscriptWrap.find('.file-block');
        let currentFileBlock = fileBlocks.eq(fileIndex);
        response?.forEach(pronunSegment => { // Loop Through Pronun Segments
            pronunSegment?.NBest?.forEach(nbEl => { // Loop Through Nbest Elements
                nbEl.Words?.forEach(pronunWord => {
                    
                    if(pronunWord.PronunciationAssessment?.ErrorType != 'None'){
                        console.log(`Pronunciation Error: ${pronunWord.PronunciationAssessment.ErrorType}`);
                        let syllables = pronunWord.Syllables;
                        let wrongPhonetics = jQuery('<span></span>');
                        let word = pronunWord.Word;
                        let offset = Math.floor(pronunWord.Offset / 10000000);
                        // console.log(pronunWord);
                        // Prepare Wrong Phonetics 
                        wrongPhonetics.append('/');
                        syllables.forEach(syllableData => {
                            let syllable = syllableData.Syllable;
                            let syllableEl = jQuery('<span></span>');
                            syllableEl.text(syllable);
                            // console.log(syllableEl);
                            if(syllableData.PronunciationAssessment.AccuracyScore < 70){
                                syllableEl.css('color', '#FF2E2E');
                            }
                            wrongPhonetics.append(syllableEl);
                        });
                        wrongPhonetics.append('/');
                        // Find the span with matching word in the current file block
                        let transcripts = currentFileBlock.find('.transcript-text');
                        transcripts.each(function() {
                            let transcript = jQuery(this);
                            let spans = transcript.find('span');
                            
                            // Log each span collection to verify it is correctly found
                            // console.log("Spans inside .transcript-text:", spans);
                            
                            spans.each(function() {
                                let span = jQuery(this);
                                let start = Math.floor(parseFloat(span.attr('data-start'))) - 1;
                                let end =  Math.floor(parseFloat(span.attr('data-end'))) + 1;
                                // Log the word in the span and the word to match
                                // console.log("Span text:", span.text().trim(), "Word to match:", word.trim());
                                
                                if (span.text().trim().replace(/^[^\w]+|[^\w]+$/g, '').toLowerCase() === word.trim().toLowerCase()) {
                                    if(start < offset && end > offset){
                                        markPronunError(span,wrongPhonetics, false ); // Add the pronun-error class or perform the action
                                    }
                                }
                            });
                        });
                    }

                });
            })
        });

        let fluencyFileBlocks = $fluencytranscriptWrap.find('.file-block');
        let currentFluencyFileBlock = fluencyFileBlocks.eq(fileIndex);

        // Array to hold fluency errors
        let fluencyErrors = [];

        response?.forEach(pronunSegment => { // Loop Through Pronun Segments
            pronunSegment?.NBest?.forEach(nbEl => { // Loop Through Nbest Elements
                nbEl.Words?.forEach((pronunWord, wordIndex) => { // Track the index of pronunWord
                    if (pronunWord.PronunciationAssessment?.Feedback?.Prosody?.Break?.ErrorTypes[0] != 'None') {
                        
                        let pauseError = pronunWord.PronunciationAssessment?.Feedback?.Prosody?.Break?.ErrorTypes[0];
                        let fluencytranscripts = currentFluencyFileBlock.find('.transcript-text');

                        fluencytranscripts.each(function () {
                            let transcript = jQuery(this);

                            // Extract the text content from the element while preserving existing HTML structure
                            let transcriptText = transcript.html();

                            // Log the transcript text to debug
                            // console.log("Transcript Text: ", transcriptText);

                            let currentPronunWord = pronunWord.Word.toLowerCase();
                            let currentWordIndex = transcript.text().split(/\s+/).indexOf(currentPronunWord);

                            // Handle the pause errors as before
                            if (currentWordIndex > 0) {
                                let previousWord = transcript.text().split(/\s+/)[currentWordIndex - 1];
                                let regexPattern = new RegExp(`\\b${previousWord}\\s*[\\.,!?]?\\s*${currentPronunWord}\\b`, 'i');

                                let updatedTranscriptText;

                                // Handle unexpected and missing breaks
                                if (pauseError == 'UnexpectedBreak') {
                                    updatedTranscriptText = transcriptText.replace(regexPattern, `${previousWord} <span class="pause-error bad-pause">
                                            <svg width="39" height="28" viewBox="0 0 39 28" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path fill-rule="evenodd" clip-rule="evenodd" d="M5.85352 11.0264V0H0.609375V27.2969H5.85351L5.85352 16.2705H33.1504V27.2969H38.3945V0H33.1504V11.0264H5.85352Z" fill="#FF4949"></path>
                                            </svg>
                                        </span> ${currentPronunWord}`);
                                } else if (pauseError == 'MissingBreak') {
                                    updatedTranscriptText = transcriptText.replace(regexPattern, `${previousWord} <span class="pause-error missing-pause">
                                            <svg width="39" height="28" viewBox="0 0 39 28" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path fill-rule="evenodd" clip-rule="evenodd" d="M6.13867 11.0264V0H0.894531V27.2969H6.13867L6.13867 16.2705H33.4355V27.2969H38.6797V0H33.4355V11.0264H6.13867Z" fill="#FFCC3D"></path>
                                            </svg>
                                        </span> ${currentPronunWord}`);
                                }
                                // Push the error details into the fluencyErrors array
                                fluencyErrors.push({
                                    previousWord: previousWord,
                                    currentPronunWord: currentPronunWord,
                                    pauseError: pauseError,
                                    index: currentWordIndex
                                });
                                // Update the transcript with the new HTML
                                transcript.html(updatedTranscriptText);
                            }
                        });
                    }
                });
            });
        });
        setTimeout(function(){
            saveFluencyErrorsInField(fluencyErrors);
        },2500,fluencyErrors);
    }
}

function markFillerWords() {
    // Define a list of filler words to be wrapped
    const fillerWords = ['hmm', 'uhm', 'uh', 'um', 'huh', 'like'];
    
    // Find all file blocks inside the transcript wrapper
    let fluencyFileBlocks = $fluencytranscriptWrap.find('.file-block');
    
    // Iterate through each file block
    fluencyFileBlocks.each(function() {
        // Find the transcript text inside each block
        jQuery(this).find('.transcript-text').each(function() {
            let transcriptText = jQuery(this).html();
            
            // Create a regular expression to match the filler words with optional commas and spaces
            const fillerWordRegex = new RegExp(`(?:\\s*,\\s*)?\\b(${fillerWords.join('|')})\\b,?`, 'gi');
            
            // Replace the matched filler words and optional commas with a span wrapping the text
            const wrappedText = transcriptText.replace(fillerWordRegex, '<span class="filler-word">$&</span>');
            
            // Update the HTML content with the wrapped filler words
            jQuery(this).html(wrappedText);
        });
    });
}

function processSavedPronunData(savedResponses){
    removePronunLoader();
    // console.log(savedResponses);
    savedResponses.forEach(savedResponse => {
        let response = JSON.parse(savedResponses).response.saved_response;
        if(!response){
            return;
        }
        response.forEach(pronunErrorObj => {
            let start = pronunErrorObj.start;
            let $word = jQuery(`span[data-start="${start}"]`);
            $word.addClass('pronun-error');
            $word.attr('id', `PRONUN_ERROR_${pronunErrorObj.errorId}`);
            buildPronunErrorPopup(pronunErrorObj,false);
            addPronunError(pronunErrorObj);
            $word.dblclick(function() {
                $word.attr('id');
                jQuery(`#pronun_error_popup_${pronunErrorObj.errorId}`).remove();
                jQuery(`#SIDEPANEL_PRONUN_ERROR_${pronunErrorObj.errorId}`).remove();
                $word.removeClass('pronun-error');
                $word.removeAttr('id');
                deletePronunErrorFromField(pronunErrorObj);
            });
    
            jQuery('.orignal-audio-play-trigger').on('click', async function(event){
                event.stopPropagation();
                let start = jQuery(this).data('start');
                start = parseFloat(start) - 0.5;
                let end = jQuery(this).data('end');
                end = parseFloat(end) + 0.5;
                let fileIndex = jQuery(this).data('file-index');
                let audio = audioFiles[fileIndex];
                    if(audio != window.loadedAudio){
                        await loadAudio(audio);
                    }
                    playSegment(start, end);
            });
            
            jQuery('.corrected-audio-play-trigger').on('click', function(event) {
                event.stopPropagation();
                event.target.style.pointerEvents = 'none';
            
                // Get the audio URL from the data attribute
                let audioUrl = jQuery(this).data('audio-url');
            
                // Check if the audio URL is valid (non-empty, not undefined, not null)
                if (audioUrl && audioUrl.trim() !== "") {
                    // Check if an audio is already playing
                    if (!jQuery(this).data('isPlaying')) {
                        // Mark the audio as playing
                        jQuery(this).data('isPlaying', true);
                        
                        // Implement audio playback logic
                        let audio = new Audio(audioUrl);
                        audio.play().catch(function(error) {
                            alert('Error playing audio: ' + error.message);
                        });
            
                        // Once the audio ends, set the flag to allow playback again
                        audio.onended = function() {
                            jQuery(event.target).data('isPlaying', false);
                            event.target.style.pointerEvents = 'all';
                        };
                    }
                } else {
                    // If no valid audio URL is available, show an alert
                    alert('Audio URL is not available or invalid.');
                    event.target.style.pointerEvents = 'all';
                }
            });
        });
    });
}

function removePronunLoader(message = "Click on the mispronounced words manually to generate feedback. Double click to uncheck."){
    let $preloader = jQuery('#pronun-suggestions img.preloader-icon');
    // Replace Loading Animation With Generate Feedback Method 
    if($preloader.length){
        $preloader.replaceWith(`<div class="pronun-help-info"><span class="pronun-pointer-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M3 3L10.07 19.97L12.58 12.58L19.97 10.07L3 3Z" stroke="#26375F" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M13 13L19 19" stroke="#26375F" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg></span>${message}</div>`);
    }
}
function setupManualFeedback(whisperResponse) {
    let fileIndex = 0;
    // Iterate over each .file-block inside $pronuntranscriptWrap
    $pronuntranscriptWrap.find('.file-block').each(function() {
        var $fileBlock = jQuery(this);
        var mergedWords = [];
        if(!whisperResponse[fileIndex]){
            return;
        }
        let whisperWords = whisperResponse[fileIndex].response.words;
        wordIndex = 0;
        // Find all .transcript-text elements within the current .file-block
        $fileBlock.find('.transcript-text').each(function() {
            var $transcript = jQuery(this);

            // Check if the transcript has already been processed
            if (!$transcript.hasClass('processed')) {
                // Split the inner text into words and filter out empty strings
                var words = $transcript.text().split(' ').filter(function(word) {
                    return word.trim() !== '';
                });

                // Add the words to the mergedWords array
                mergedWords = mergedWords.concat(words);

                // Clear the existing text
                $transcript.empty();
                // Wrap each word with a span and append it back to the transcript
                jQuery.each(words, function(index, word) {
                    // Create a span for each word
                    var $wordSpan = jQuery(`<span data-start="${whisperWords[wordIndex].start}" data-end="${whisperWords[wordIndex].end}" data-file-index="${fileIndex}"></span>`).text(word);

                    // Add space after each word except the last
                    // $wordSpan.css('margin-right', '4px');

                    // Add hover style to underline word
                    $wordSpan.hover(
                        function() {
                            jQuery(this).css('text-decoration', 'underline');
                            jQuery(this).css('cursor', 'pointer');
                        },
                        function() {
                            jQuery(this).css('text-decoration', 'none');
                        }
                    );

                    // Add onclick event for each word
                    $wordSpan.on('click', function(event) {
                        event.stopPropagation();
                        let word = jQuery(this);
                        markPronunError(word);
                    });

                    // Append the word span to the transcript element
                    $transcript.append($wordSpan);
                    wordIndex++;
                });


                // Mark this transcript as processed
                $transcript.addClass('processed');
            }
        });

        // Log the merged array of words for the current .file-block
        // console.log(`Merged words for file block ${fileIndex}:`, mergedWords);
        fileIndex++;
    });
}

async function markPronunError($word,wrongPhonetics = null, manuallyMarked = true) {
    // Check if the word already has the 'pronun-error' class
    if ($word.hasClass('pronun-error')) {
        // Remove Active Class From All Active Elements 
        // console.log('Removing Active Class');
        jQuery('.pronun-error').removeClass('active');
        jQuery('.pronun-error-popup').css({
            top: 0 + 'px', // 10px below the cursor
            left: -100 + 'vw',
        });
        // Get The Popup ID
        let popupId = $word.attr('id').replace('PRONUN_ERROR_', 'pronun_error_popup_');
        // console.log(popupId);
        // Check if the popup exists
        let $popup = jQuery(`#${popupId}`);
        // console.log($popup);
        if ($popup.length) { // Ensure the popup exists in the DOM
            // Add the active class and position the popup
            $popup.addClass('active');
            $popup.css({
                top: pronunciationMouseY + 10 + 'px', // 10px below the cursor
                left: pronunciationMouseX + 'px'
            });
        } else {
            console.warn(`Popup with ID ${popupId} not found.`);
        }
        return;
    }
    
    let start = $word.attr('data-start');
    let end = $word.attr('data-end');
    let fileIndex = $word.attr('data-file-index');
    let text = $word.text().trim();
    text = text.replace(/^[^\w]+|[^\w]+$/g, '');
    
    // Add loading class
    $word.addClass('word-pronunciation-loading');
    
    try {
        // Fetch data from the dictionary API
        let dictionaryAPIData = await fetch(`https://api.dictionaryapi.dev/api/v2/entries/en/${text}`)
            .then(res => res.json());
        let correctPronunAudio = '';
        let correctPhonetic = '';
        let PhoneticsArray = dictionaryAPIData[0].phonetics;
        let valuesAssigned = false;
        // First pass: Check if both audio and text are available in the same object
        for (let i = 0; i < PhoneticsArray.length; i++) {
            let phonetic = PhoneticsArray[i];
            
            if (phonetic.audio && phonetic.text) {
                // If both audio and text are found in the same object, assign them
                correctPronunAudio = phonetic.audio;
                correctPhonetic = phonetic.text;
                valuesAssigned = true;
                break; // Exit the loop after finding the first valid pair
            }
        }

        // Second pass: If no valid pair found, try to find text and audio separately
        if (!valuesAssigned) {
            let foundText = false;
            let foundAudio = false;

            for (let i = 0; i < PhoneticsArray.length; i++) {
                let phonetic = PhoneticsArray[i];
                // Assign the first available text if not already found
                if (!foundText && phonetic.text) {
                    // console.log(`Assigning Value ${phonetic.text}`);
                    correctPhonetic = phonetic.text;
                    foundText = true;
                }
                
                // Assign the first available audio if not already found
                if (!foundAudio && phonetic.audio) {
                    // console.log(`Assigning Value ${phonetic.audio}`);
                    correctPronunAudio = phonetic.audio;
                    foundAudio = true;
                }

                // Break the loop if both values have been found
                if (foundText && foundAudio) {
                    valuesAssigned = true;
                    break;
                }
            }
        }


        // Increment the pronun error counter
        pronunErrorCounter++;
        // Add the pronun-error class and set the ID
        $word.addClass('pronun-error');
        $word.attr('id', `PRONUN_ERROR_${pronunErrorCounter}`);
        // Build Error Popup 
        let pronunErrorObj = {
            start:start,
            end:end,
            text:text,
            correctPronunAudio : correctPronunAudio,
            correctPhonetic : correctPhonetic,
            fileIndex : fileIndex,
            errorId : pronunErrorCounter,
            wrongPhonetics: null
        }

        if(wrongPhonetics){
            pronunErrorObj.wrongPhonetics = wrongPhonetics.html();
        }

        buildPronunErrorPopup(pronunErrorObj,manuallyMarked);
        addPronunError(pronunErrorObj);
        setTimeout(function(){
            savePronunErrorInField(pronunErrorObj);
        },2500,pronunErrorObj);
        $word.dblclick(function() {
            $word.attr('id');
            jQuery(`#pronun_error_popup_${pronunErrorObj.errorId}`).remove();
            jQuery(`#SIDEPANEL_PRONUN_ERROR_${pronunErrorObj.errorId}`).remove();
            $word.removeClass('pronun-error');
            $word.removeAttr('id');
            deletePronunErrorFromField(pronunErrorObj);
        });

        jQuery('.orignal-audio-play-trigger').on('click', async function(event){
            event.stopPropagation();
            let start = jQuery(this).data('start');
            start = parseFloat(start) - 0.5;
            let end = jQuery(this).data('end');
            end = parseFloat(end) + 0.5;
            let fileIndex = jQuery(this).data('file-index');
            let audio = audioFiles[fileIndex];
                if(audio != window.loadedAudio){
                    await loadAudio(audio);
                }
                playSegment(start, end);
        });
        
        jQuery('.corrected-audio-play-trigger').on('click', function(event) {
            event.stopPropagation();
            event.target.style.pointerEvents = 'none';
        
            // Get the audio URL from the data attribute
            let audioUrl = jQuery(this).data('audio-url');
        
            // Check if the audio URL is valid (non-empty, not undefined, not null)
            if (audioUrl && audioUrl.trim() !== "") {
                // Check if an audio is already playing
                if (!jQuery(this).data('isPlaying')) {
                    // Mark the audio as playing
                    jQuery(this).data('isPlaying', true);
                    
                    // Implement audio playback logic
                    let audio = new Audio(audioUrl);
                    audio.play().catch(function(error) {
                        alert('Error playing audio: ' + error.message);
                    });
        
                    // Once the audio ends, set the flag to allow playback again
                    audio.onended = function() {
                        jQuery(event.target).data('isPlaying', false);
                        event.target.style.pointerEvents = 'all';
                    };
                }
            } else {
                // If no valid audio URL is available, show an alert
                alert('Audio URL is not available or invalid.');
                event.target.style.pointerEvents = 'all';
            }
        });

    } catch (error) {
        // Handle any errors that occur during fetch
        console.error('Error fetching dictionary API data:', error);
    } finally {
        // Remove loading class
        $word.removeClass('word-pronunciation-loading');
    }
}


function addPronunError(pronunErrorObj){
    // console.log('Adding Error in List');
    // Destructure pronunErrorObj to extract details
    const { start, end, text, correctPronunAudio, correctPhonetic,fileIndex,errorId, wrongPhonetics } = pronunErrorObj;
    let textContent = text;
    if(wrongPhonetics){
        textContent = wrongPhonetics;
    }
    // Create the Side Panel Element
    let $sidePanelPronunError = jQuery(`
        <div class="pronun_error" id="SIDEPANEL_PRONUN_ERROR_${errorId}">
    <div class="sidepanel-orignal-pronun" data-text="${text}">
        ${textContent}
    </div>
    <div class="side-panel-pronun-arrow">
        <svg width="24" height="25" viewBox="0 0 24 25" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M5 12.9453H19" stroke="#26375F" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M12 5.94531L19 12.9453L12 19.9453" stroke="#26375F" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </div>
    <div class="sidepanel-correct-pronun">
        ${correctPhonetic}
    </div>
    <div class="side-panel-actions-wrap">
        <div class="sidepanel-corrected-audio">
            <!-- Corrected Audio Play Trigger -->
            <div class="corrected-audio-play-trigger" data-audio-url="${correctPronunAudio}">
                <svg width="39" height="39" viewBox="0 0 39 39" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="1.19531" y="1.44531" width="37" height="37" rx="18.5" stroke="black"/>
                    <path d="M18.6953 12.9453L13.6953 16.9453H9.69531V22.9453H13.6953L18.6953 26.9453V12.9453Z" stroke="#26375F" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M26.7654 12.8755C28.6401 14.7508 29.6932 17.2938 29.6932 19.9455C29.6932 22.5971 28.6401 25.1402 26.7654 27.0155M23.2354 16.4055C24.1727 17.3431 24.6993 18.6147 24.6993 19.9405C24.6993 21.2663 24.1727 22.5379 23.2354 23.4755" stroke="#26375F" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>                        
            </div>
        </div>
        <div class="sidepanel-orignal-audio">
            <!-- Original Audio Play Trigger -->
            <div class="orignal-audio-play-trigger" data-start="${start}" data-end="${end}" data-file-index="${fileIndex}">
                <svg width="39" height="39" viewBox="0 0 39 39" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="1.19531" y="0.945312" width="37" height="37" rx="18.5" stroke="black"/>
                    <g clip-path="url(#clip0_2014_7756)">
                    <path d="M23.6953 28.4453V26.4453C23.6953 25.3844 23.2739 24.367 22.5237 23.6169C21.7736 22.8667 20.7562 22.4453 19.6953 22.4453H12.6953C11.6344 22.4453 10.617 22.8667 9.86689 23.6169C9.11674 24.367 8.69531 25.3844 8.69531 26.4453V28.4453" stroke="#26375F" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M16.1953 18.4453C18.4045 18.4453 20.1953 16.6545 20.1953 14.4453C20.1953 12.2362 18.4045 10.4453 16.1953 10.4453C13.9862 10.4453 12.1953 12.2362 12.1953 14.4453C12.1953 16.6545 13.9862 18.4453 16.1953 18.4453Z" stroke="#26375F" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M25.6953 15.4453L30.6953 20.4453" stroke="#26375F" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M30.6953 15.4453L25.6953 20.4453" stroke="#26375F" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </g>
                    <defs>
                    <clipPath id="clip0_2014_7756">
                        <rect width="24" height="24" fill="white" transform="translate(7.69531 7.44531)"/>
                    </clipPath>
                    </defs>
                </svg>
            </div>
        </div>
    </div>
</div>
        `);

       $pronunErrorsWrap.append($sidePanelPronunError);
}


function buildPronunErrorPopup(pronunErrorObj,mauallyMarked = false) {
    // Destructure pronunErrorObj to extract details
    const { start, end, text, correctPronunAudio, correctPhonetic,fileIndex,errorId } = pronunErrorObj;

    // Create the popup element dynamically
    let $pronunErrorPopup = jQuery(`
        <div class="pronun-error-popup" id="pronun_error_popup_${errorId}">
            <div class="pronun-error-inner">
                <div class="pronun-error-popup-row">
                    <div class="popup-orignal-pronun-label">You Said</div>
                    <div class="popup-orignal-phoneme">${text}</div>
                    <div class="popup-orignal-audio">
                        <!-- Original Audio Play Trigger -->
                        <div class="orignal-audio-play-trigger" data-start="${start}" data-end="${end}" data-file-index="${fileIndex}">
                            <svg width="39" height="39" viewBox="0 0 39 39" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="1.19531" y="0.945312" width="37" height="37" rx="18.5" stroke="black"/>
                                <g clip-path="url(#clip0_2014_7756)">
                                <path d="M23.6953 28.4453V26.4453C23.6953 25.3844 23.2739 24.367 22.5237 23.6169C21.7736 22.8667 20.7562 22.4453 19.6953 22.4453H12.6953C11.6344 22.4453 10.617 22.8667 9.86689 23.6169C9.11674 24.367 8.69531 25.3844 8.69531 26.4453V28.4453" stroke="#26375F" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M16.1953 18.4453C18.4045 18.4453 20.1953 16.6545 20.1953 14.4453C20.1953 12.2362 18.4045 10.4453 16.1953 10.4453C13.9862 10.4453 12.1953 12.2362 12.1953 14.4453C12.1953 16.6545 13.9862 18.4453 16.1953 18.4453Z" stroke="#26375F" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M25.6953 15.4453L30.6953 20.4453" stroke="#26375F" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M30.6953 15.4453L25.6953 20.4453" stroke="#26375F" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </g>
                                <defs>
                                <clipPath id="clip0_2014_7756">
                                    <rect width="24" height="24" fill="white" transform="translate(7.69531 7.44531)"/>
                                </clipPath>
                                </defs>
                            </svg>
                        </div>
                    </div>
                </div>
                <div class="pronun-error-popup-row">
                    <div class="popup-corrected-pronun-label">Correction</div>
                    <div class="popup-corrected-phoneme">${correctPhonetic}</div>
                    <div class="popup-corrected-audio">
                        <!-- Corrected Audio Play Trigger -->
                        <div class="corrected-audio-play-trigger" data-audio-url="${correctPronunAudio}">
                            <svg width="39" height="39" viewBox="0 0 39 39" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="1.19531" y="1.44531" width="37" height="37" rx="18.5" stroke="black"/>
                                <path d="M18.6953 12.9453L13.6953 16.9453H9.69531V22.9453H13.6953L18.6953 26.9453V12.9453Z" stroke="#26375F" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M26.7654 12.8755C28.6401 14.7508 29.6932 17.2938 29.6932 19.9455C29.6932 22.5971 28.6401 25.1402 26.7654 27.0155M23.2354 16.4055C24.1727 17.3431 24.6993 18.6147 24.6993 19.9405C24.6993 21.2663 24.1727 22.5379 23.2354 23.4755" stroke="#26375F" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>                        
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `);

    // Append the generated popup to the body
    jQuery('body').append($pronunErrorPopup);
    // Remove Active Classes From Other Pronun Popup 
    jQuery('.pronun-error-popup').removeClass('active');
    jQuery('.pronun-error-popup').css({
        top: 0 + 'px', // 10px below the cursor
        left: -100 + 'vw',
    });
    if(mauallyMarked){
        $pronunErrorPopup.addClass('active');
        $pronunErrorPopup.css({
            top: pronunciationMouseY + 10 + 'px', // 10px below the cursor
            left: pronunciationMouseX + 'px',
        });
    }
}

$document.on('click', function () {
    jQuery('.pronun-error-popup').removeClass('active');
    jQuery('.pronun-error-popup').css({
        top: 0 + 'px', // 10px below the cursor
        left: -100 + 'vw',
    });
});

function deletePronunErrorFromField(pronunErrorObj) {
    let formId = parseInt(pronunData.formId);
    let entryId = parseInt(pronunData.entryId);
    let restUrl = pronunData.restUrl + 'writify/v1/delete-pronun-error/'; // REST route URL

    // Prepare the data to send to the REST API
    let data = {
        formId: formId,
        entryId: entryId,
        errorId: pronunErrorObj.errorId, // Error ID to delete
    };

    // Send the POST request
    fetch(restUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data), // Send the data as JSON
    })
    .then(response => response.json())
    .then(responseData => {
        if (responseData.status === 'success') {
            console.log('Pronunciation error deleted:', responseData);
        } else {
            console.log('Error:', responseData);
        }
    })
    .catch(error => {
        console.error('Error deleting pronunciation error:', error);
    });
}

function saveFluencyErrorsInField(fluencyErrorsArray) {
    let formId = parseInt(pronunData.formId);
    let entryId = parseInt(pronunData.entryId);
    let restUrl = pronunData.restUrl + 'writify/v1/save-fluency-errors/'; // REST route URL

    // Prepare the data to send to the REST API
    let data = {
        formId: formId,
        entryId: entryId,
        fluencyErrors: fluencyErrorsArray,
    };

    // Send the POST request
    fetch(restUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data), // Send the data as JSON
    })
    .then(response => response.json())
    .then(responseData => {
        if (responseData.status === 'success') {
            console.log('Fluency errors saved:', responseData);
        } else {
            console.log('Error:', responseData);
        }
    })
    .catch(error => {
        console.error('Error saving fluency errors:', error);
    });
}


function savePronunErrorInField(pronunErrorObj) {
    let formId = parseInt(pronunData.formId);
    let entryId = parseInt(pronunData.entryId);
    let restUrl = pronunData.restUrl + 'writify/v1/save-pronun-error/'; // REST route URL

    // Prepare the data to send to the REST API
    let data = {
        formId: formId,
        entryId: entryId,
        pronunErrorObj: pronunErrorObj,
    };

    // Send the POST request
    fetch(restUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data), // Send the data as JSON
    })
    .then(response => response.json())
    .then(responseData => {
        if (responseData.status === 'success') {
            console.log('Pronunciation error saved:', responseData);
        } else {
            console.log('Error:', responseData);
        }
    })
    .catch(error => {
        console.error('Error saving pronunciation error:', error);
    });
}