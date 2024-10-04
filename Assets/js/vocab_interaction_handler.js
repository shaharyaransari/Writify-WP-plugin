/**
 * Script Name: Vocab Interaction Handler
 * Version: 1.0.0
 * Last Updated: 13-11-2023
 * Author: bi1101
 * Description: Turn static vocab <li> into intereactive clickable div.
 */


// Store frequently used selectors
const $document = jQuery(document);
const $myTextDiv = jQuery("#my-text");

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

            const matches = updatedText.match(/<span class="original-vocab">(.*?)<\/span><span class="arrow">-\><\/span> <span class="improved-vocab">(.*?)<\/span>/);

            if (matches) {
                const originalVocab = matches[1];

                // Remove any existing highlighting from the content
                const markElements = $myTextDiv.find("mark");
                if (markElements.length > 0) {
                    markElements.contents().unwrap();
                }
                // Escape any special characters in the original vocab
                const escapedOriginalVocab = originalVocab.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");

                // Highlight the occurrences of the original vocabulary in the content
                const unhighlightedText = $myTextDiv.html();
                const highlightedText = unhighlightedText.replace(new RegExp(escapedOriginalVocab, "gi"), function (matched) {
                    return `<mark>${matched}</mark>`;
                });
                $myTextDiv.html(highlightedText);

                // Scroll to the first occurrence of the highlighted vocabulary
                const firstMark = $myTextDiv.find("mark:first");

                if (firstMark.length) {
                    const currentScroll = $myTextDiv.scrollTop();
                    const markTopRelative = firstMark.position().top;
                    $myTextDiv.animate({
                        scrollTop: currentScroll + markTopRelative - 140
                    }, 500);
                }

                // Set up a hover event for the highlighted vocabulary to remove the highlighting
                const marks = $myTextDiv.find("mark");

                marks.hover(function () {
                    jQuery(this).fadeOut(500, function () {
                        const originalWord = jQuery(this).text();
                        jQuery(this).replaceWith(originalWord);
                    });
                });

                // Show or hide specific elements within the clicked list item
                jQuery(this).find(".original-vocab, .arrow, .or, .improved-vocab, .explanation").slideDown(200);
                jQuery(this).find(".short-explanation").hide();

                // Add the "expanded" class to the clicked list item
                jQuery(this).addClass("expanded");
            }
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

            // Unwrap the <mark> tags
            $myTextDiv.find('mark').each(function () {
                const text = jQuery(this).text();
                jQuery(this).replaceWith(text);
            });

            // Get the unmarked text in the #my-text div
            let unmarkedText = $myTextDiv.html();

            // Escape any special characters in the original vocab
            const escapedOriginalVocab = originalVocab.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");

            // Replace the original vocab with the improved vocab in the unmarked text and make the improved vocab bold
            const updatedText = unmarkedText.replace(new RegExp(escapedOriginalVocab, "gi"), `<b>${improvedVocab}</b>`);
            $myTextDiv.html(updatedText);

            // Make the li.upgrade_vocab element disappear with a fade out animation
            jQuery(this).closest(".upgrade_vocab").fadeOut();
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
        const $this = jQuery(this);
        const text = $this.text().trim();
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

    // Remove the "expanded" class from all list items with the "upgrade_vocab" class
    jQuery(".upgrade_vocab").removeClass("expanded");
});
