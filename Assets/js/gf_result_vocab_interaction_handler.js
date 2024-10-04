/**
 * Script Name: Vocab Interaction Handler
 * Version: 1.0.0
 * Last Updated: 13-11-2023
 * Author: bi1101
 * Description: Turn static vocab <li> into intereactive clickable div.
 */


// Store frequently used selectors
const $document = jQuery(document);
const $transcriptWrap = jQuery('#vocab-transcript-wrap');
let vocabErrorCounter = 0;


/**
 * Formats the given text according to vocab upgrade pattern.
 * 
 * @param {string} text - The text to be formatted.
 * @returns {string|null} - The formatted text or null if the text does not match the pattern.
 */
function formatText(text) {
    // Updated regex to handle optional line break before "->"
    const format = /".*"\s*-\>\s*".*"(\sor\s".*")?\s*(Explanation|Giải thích): .*/;
    if (!format.test(text)) return null;

    // Extract explanation using either "Explanation" or "Giải thích"
    const explanationMatch = text.match(/(Explanation|Giải thích): (.*)/);
    const explanation = explanationMatch && explanationMatch[2] ? explanationMatch[2] : '';
    const firstSentence = explanation.match(/[^\.!\?]+[\.!\?]+/g)[0];
    const secondImprovedVocabMatch = text.match(/or "(.*)"/);
    let secondImprovedVocab = '';
    if (secondImprovedVocabMatch) {
        secondImprovedVocab = `<span class="or"> or </span><span class="improved-vocab">${secondImprovedVocabMatch[1]}</span>`;
    }

    return text.replace(/"(.*)"\s*-\>\s*"(.*?)"(\sor\s".*")?\s*((?:Explanation|Giải thích): .*)/, `<span class="original-vocab">$1</span><span class="arrow">-\></span> <span class="improved-vocab">$2</span>${secondImprovedVocab}<span class="short-explanation"> · ${firstSentence}</span><br><span class="explanation">$4</span>`);
}

/**
 * Creates a new div element with upgrade_vocab and HTML content.
 * @param {string} html - The HTML content to be inserted into the div.
 * @returns {jQuery} - The newly created div element.
 */
function createNewDivWithClass(html) {
    return jQuery('<div/>', {
        class: 'upgrade_vocab',
        id: `VOCAB_ERROR_${vocabErrorCounter}`,
        html: html
    });
}

/**
 * Hides certain elements and shows others when interacting.
 * @param {jQuery} $newDiv - The jQuery object representing the div element.
 */
function hideAndShowElements($newDiv) {
    const $elementsToHide = $newDiv.find(".arrow, .or, .improved-vocab, .explanation");
    const $elementsToShow = $newDiv.find(".original-vocab, .short-explanation");
    $elementsToHide.hide();
    $elementsToShow.slideDown(200);
}

/**
 * Adds a click event listener to upgrade_vocab element.
 * 
 * @param {jQuery} $newDiv - The jQuery object representing the div element.
 * @param {string} updatedText - The updated text to be used for processing.
 */
function addClickEventListenerToDiv($newDiv, updatedText) {
    $newDiv.on('click', function (event) {
        event.stopPropagation();

        if (!jQuery(this).hasClass("expanded")) {
            // Hide elements and show the short explanation of other list items with the "upgrade_vocab" class
            jQuery(".upgrade_vocab").not(this).find(".arrow, .or, .improved-vocab, .explanation").hide();
            jQuery(".upgrade_vocab").not(this).find(".original-vocab, .short-explanation").show();
            jQuery(".upgrade_vocab").not(this).removeClass("expanded");
            let errorId = this.id.replace('VOCAB_ERROR_','ERROR_');
            
            // Remove Existing Highlights
            $transcriptWrap.find(".vocab-error").removeClass("vocab-highlighted");
            
            // Add Class To Current one
            const $errorElement = $transcriptWrap.find(`.vocab-error#${errorId}`).addClass("vocab-highlighted");
            
            // Scroll the element into view centered
            $errorElement[0].scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
            
            // Show or hide specific elements within the clicked list item
            jQuery(this).find(".original-vocab, .arrow, .or, .improved-vocab, .explanation").slideDown(200);
            jQuery(this).find(".short-explanation").hide();

            // Add the "expanded" class to the clicked list item
            jQuery(this).addClass("expanded");
        }
    });
}

function addClickEventListenerToVocabError() {
    $transcriptWrap.find('.vocab-error').on('click', function (event) {
        event.stopPropagation(); // Stop the event from bubbling up

        const vocabErrorId = jQuery(this).attr('id');
        const vocabDivId = vocabErrorId.replace('ERROR_', 'VOCAB_ERROR_');
        const vocabPopupId = vocabErrorId.replace('ERROR_', 'vocab_error_popup_');
        // Find the corresponding div with the same vocabDivId
        const $correspondingDiv = jQuery(`#${vocabDivId}`);
        const $correspondingPopup = jQuery(`#${vocabPopupId}`);
        
        jQuery('.vocab-error-popup').removeClass('active');
        jQuery('.vocab-error-popup').css({
            position: 'absolute',
            bottom:0, // Just below the click location
            left: '-100vw',
            opacity: 0
        });
        $correspondingPopup.addClass('active');
        // Position the popup just below the click location
        const clickX = event.pageX;
        const clickY = event.pageY;
        $correspondingPopup.css({
            position: 'absolute',
            top: clickY + 10 + 'px', // Just below the click location
            left: clickX + 'px',
            opacity: 1
        }); // Optionally animate the popup appearance

        if (!$correspondingDiv.hasClass("expanded")) {
            // Hide elements and show the short explanation of other list items with the "upgrade_vocab" class
            jQuery(".upgrade_vocab").not($correspondingDiv).find(".arrow, .or, .improved-vocab, .explanation").hide();
            jQuery(".upgrade_vocab").not($correspondingDiv).find(".original-vocab, .short-explanation").show();
            jQuery(".upgrade_vocab").not($correspondingDiv).removeClass("expanded");

            // Remove existing highlights
            $transcriptWrap.find(".vocab-error").removeClass("vocab-highlighted");

            // Add class to the current one
            jQuery(this).addClass("vocab-highlighted");

            // Scroll the vocab-error element into view centered
            this.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });

            // Show or hide specific elements within the clicked list item
            $correspondingDiv.find(".original-vocab, .arrow, .or, .improved-vocab, .explanation").slideDown(200);
            $correspondingDiv.find(".short-explanation").hide();

            // Add the "expanded" class to the corresponding div
            $correspondingDiv.addClass("expanded");
        }
    });
}

/**
 * Adds a click event listener to the improved vocab element.
 * 
 * @param {jQuery} $newDiv - The jQuery object representing the new div element.
 * @param {string} updatedText - The updated text containing the original and improved vocab.
 */
function addClickEventListenerToImprovedVocab($newDiv, updatedText) {
    $newDiv.find(".improved-vocab").on('click', function (event) {
        event.stopPropagation(); // Prevent the event from bubbling up to the document

        // Extract the original vocab from the updated text
        const originalVocabMatch = updatedText.match(/<span class="original-vocab">(.*?)<\/span><span class="arrow">-\><\/span>/);
        if (originalVocabMatch) {
            const originalVocab = originalVocabMatch[1];

            // Extract the improved vocab from the clicked element
            const improvedVocab = jQuery(this).text();

            let errorId = jQuery(this).closest(".upgrade_vocab").attr("id").replace('VOCAB_ERROR_','ERROR_');

            let unmarkedText = $transcriptWrap.find(`.vocab-error#${errorId}`).html();

            // Escape any special characters in the original vocab
            const escapedOriginalVocab = originalVocab.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");

            // Replace the original vocab with the improved vocab in the unmarked text and make the improved vocab bold
            const updatedText = unmarkedText.replace(new RegExp(escapedOriginalVocab, "gi"), `<b>${improvedVocab}</b>`);
            $transcriptWrap.find(`.vocab-error#${errorId}`).html(updatedText).removeClass('vocab-error');

            // Make the li.upgrade_vocab element disappear with a fade out animation
            jQuery(this).closest(".upgrade_vocab").fadeOut();

            // Check if All Errors Replaced or Not
            // If Yes Display a Message in vocab-suggestions div.
            if ($transcriptWrap.find('.vocab-error').length === 0) {
                // Append a message and hide it initially
                jQuery('#vocab-suggestions').append('<div class="vocab-resolved-message" style="display:none;">All Vocabulary Errors Resolved</div>');
                jQuery('.vocab-resolved-message').fadeIn(1000);
            }
        }
    });
}

/**
 * Adds an upgrade vocab class to the specified div element.
 * 
 * @param {jQuery} div - The div element to add the upgrade vocab class to.
 */
function addUpgradeVocabClass(div) {
    const listItems = div.find("li");

    listItems.each(function () {
        vocabErrorCounter++;
        const $this = jQuery(this);
        const text = $this.text().trim();
        // Highlight Vocabulary Errors in Vocab Transcript
        hightlightVocabErrors(text);
        const updatedText = formatText(text);
        if (updatedText) {
            $this.html(updatedText);
            const $newDiv = createNewDivWithClass(this.innerHTML);
            $this.replaceWith($newDiv);
            hideAndShowElements($newDiv);
            addClickEventListenerToDiv($newDiv, updatedText);
            addClickEventListenerToImprovedVocab($newDiv, updatedText);
        }
    });
    addClickEventListenerToVocabError();
}

function hightlightVocabErrors(text) {
    // Split text based on '->' to separate the original and improved vocab
    const [vocabLine, explanationLine] = text.split('Explanation:');

    // Extract original and improved vocab using jQuery map function
    const [originalVocab, improvedVocab] = vocabLine.split('->').map(function(item) {
        return jQuery.trim(item);  // Use jQuery's trim function
    });

    // Handle context if present
    let context = null;
    const explanationParts = explanationLine.split('Context:');
    const explanation = jQuery.trim(explanationParts[0]);

    if (explanationParts.length > 1) {
        context = jQuery.trim(explanationParts[1]);
    }

    // Creating the vocabErrorObj
    let vocabErrorObj = {
        originalVocab: originalVocab.replace(/"/g, ''), // Remove quotes if needed
        improvedVocab: improvedVocab.replace(/"/g, ''),
        explanation: explanation.replace(/"/g, ''),
        errorId : vocabErrorCounter,
        context: context ? context : null
    };

    buildVocabErrorPopup(vocabErrorObj);

    // Loop through transcript text and replace only the first occurrence
    $transcriptWrap.find('.file-block .transcript-text').each(function(index, element) {
        let text = jQuery(element).html();

        // Create a regular expression for the first occurrence (without the global flag)
        const regex = new RegExp(`(${vocabErrorObj.originalVocab})`, 'i');
        text = text.replace(regex, `<span class="vocab-error" id="ERROR_${vocabErrorCounter}">$1</span>`);

        // Update the element's content with the highlighted vocab
        jQuery(element).html(text);
    });
}


function buildVocabErrorPopup(vocabErrorObj) {

    // Helper function to create individual popup rows
    function createPopupRow(labelText, contentText, contentClass = "orignal") {
        // Create row div
        const rowDiv = document.createElement('div');
        rowDiv.className = 'vocab-error-popup-row';

        // Create label div
        const labelDiv = document.createElement('div');
        labelDiv.className = `popup-${contentClass}-vocab-label`;
        labelDiv.textContent = labelText;

        // Create content div
        const contentDiv = document.createElement('div');
        contentDiv.className = `popup-${contentClass}-vocab`;
        contentDiv.innerHTML = `<span>${contentText}</span>`;

        // Append label and content to the row
        rowDiv.appendChild(labelDiv);
        rowDiv.appendChild(contentDiv);
        return rowDiv;
    }
    // Create main div for the popup
    const vocabError = document.createElement('div');
    vocabError.className = 'vocab-error-popup';
    vocabError.id = `vocab_error_popup_${vocabErrorObj.errorId}`;

    // Create inner div with dynamic id
    const innerWrap = document.createElement('div');
    innerWrap.className = 'vocab-error-inner';

    // You Said Row
    const originalVocabRow = createPopupRow('You Said', vocabErrorObj.originalVocab, 'orignal');

    // Suggestions Row
    const improvedVocabRow = createPopupRow('Suggestions', vocabErrorObj.improvedVocab, 'suggested');

    // Explanation Row
    const explanationRow = createPopupRow('Explanation', vocabErrorObj.explanation, 'exp');

    // Append rows to inner wrapper
    innerWrap.appendChild(originalVocabRow);
    innerWrap.appendChild(improvedVocabRow);
    innerWrap.appendChild(explanationRow);

    // Append inner wrapper to the main div
    vocabError.appendChild(innerWrap);

    // Append the main div to the body
    document.body.appendChild(vocabError);

    // Add Event Listner to ImprovedVocab
    // Add Event Listener to the suggested vocab
    const suggestedVocabElement = vocabError.querySelector('.popup-suggested-vocab span');
    suggestedVocabElement.addEventListener('click', function (event) {
        // Extract vocab error ID from vocabErrorObj
        const errorId = vocabErrorObj.errorId;

        // Handle the click event logic, you can customize it based on your requirements
        const originalVocab = vocabErrorObj.originalVocab;
        const improvedVocab = this.textContent;

        // Example: Log the action or perform other operations
        console.log(`Improved vocab clicked for Error ID: ${errorId}`);
        console.log(`Original Vocab: ${originalVocab}`);
        console.log(`Improved Vocab: ${improvedVocab}`);

        // Perform any actions such as replacing the text, marking as resolved, etc.
        // For instance, you could replace the original vocab in the main text with the improved vocab.
        let unmarkedText = $transcriptWrap.find(`.vocab-error#ERROR_${errorId}`).html();
        console.log(unmarkedText);
            // Escape any special characters in the original vocab
            const escapedOriginalVocab = originalVocab.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");

            // Replace the original vocab with the improved vocab in the unmarked text and make the improved vocab bold
            const updatedText = unmarkedText.replace(new RegExp(escapedOriginalVocab, "gi"), `<b>${improvedVocab}</b>`);
            $transcriptWrap.find(`.vocab-error#ERROR_${errorId}`).html(updatedText).removeClass('vocab-error');

            // Make the li.upgrade_vocab element disappear with a fade out animation
            jQuery(`.upgrade_vocab#VOCAB_ERROR_${errorId}`).fadeOut();

            // Check if All Errors Replaced or Not
            // If Yes Display a Message in vocab-suggestions div.
            if ($transcriptWrap.find('.vocab-error').length === 0) {
                // Append a message and hide it initially
                jQuery('#vocab-suggestions').append('<div class="vocab-resolved-message" style="display:none;">All Vocabulary Errors Resolved</div>');
                jQuery('.vocab-resolved-message').fadeIn(1000);
            }
    });
}

// Add click event listener to the #accept_all button
jQuery("#accept_all").on('click', function () {
    // Trigger the click event on all li.upgrade_vocab elements
    jQuery("div.improved-vocab").click();
});

// Add a click event listener to the document
$document.on('click', function () {
    // Hide the arrow, improved vocab, explanation, and show the short explanation of all list items with the "upgrade_vocab" class
    jQuery(".upgrade_vocab").find(".arrow, .or, .improved-vocab, .explanation").hide();
    jQuery(".upgrade_vocab").find(".short-explanation").show();
    $transcriptWrap.find(".vocab-error").removeClass("vocab-highlighted");
    jQuery('.vocab-error-popup').removeClass('active');
    jQuery('.vocab-error-popup').css({
        position: 'absolute',
        bottom:0, // Just below the click location
        left: '-100vw',
        opacity: 0
    });
    // Remove the "expanded" class from all list items with the "upgrade_vocab" class
    jQuery(".upgrade_vocab").removeClass("expanded");
});