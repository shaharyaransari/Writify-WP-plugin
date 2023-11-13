/**
 * Script Name: Writify Event Stream
 * Version: 1.0.0
 * Last Updated: 13-11-2023
 * Author: bi1101
 * Description: Initiate event stream & process streaming data.
 */


var div_index = 0, div_index_str = '';
var buffer = ""; // Buffer for holding messages
var md = new Remarkable();

// Fetch the user role using fetch API
fetch("<?php echo admin_url('admin-ajax.php'); ?>", {
    method: 'POST',
    headers: {
        'Content-Type': 'application/x-www-form-urlencoded'
    },
    body: 'action=writify_get_user_role'
})
    .then(response => response.json())
    .then(data => {
        const userIdentifier = data.role;

        // Now initiate the EventSource with the userIdentifier in the query params
        const formId = <? php echo json_encode($form_id); ?>;
        const entryId = <? php echo json_encode($entry_id); ?>;
        const sourceUrl = `/wp-json/writify/v1/event_stream_openai?form_id=${formId}&entry_id=${entryId}&user_identifier=${userIdentifier}`;

        const source = new EventSource(sourceUrl);
        source.onmessage = function (event) {
            if (event.data == "[ALLDONE]") {
                source.close();
            } else if (event.data.startsWith("[DIVINDEX-")) {
                div_index_str = event.data.replace("[DIVINDEX-", "").replace("]", "");
                div_index = parseInt(div_index_str);
                console.log(div_index);
                jQuery('.response-div-' + (div_index)).css('display', 'flex');
                jQuery('.response-div-divider' + (div_index)).show();
            } else if (event.data == "[DONE]") {
                // When a message is done, convert the buffer to HTML and display it
                var html = md.render(buffer);
                jQuery('.response-div-' + div_index).find('.preloader-icon').hide();
                var current_div = jQuery('.response-div-' + div_index).find('.elementor-shortcode');
                current_div.html(html); // Replace the current HTML content with the processed markdown

                jQuery.when(current_div.html(html)).then(function () {
                    // Add the "upgrade_vocab" class to the <li> elements that match the format
                    addUpgradeVocabClass(current_div);
                });

                // Clear the buffer
                buffer = "";
            } else {
                // Add the message to the buffer
                text = JSON.parse(event.data).choices[0].delta.content;
                if (text !== undefined) {
                    buffer += text;
                    // Convert the buffer to HTML and display it
                    var html = md.render(buffer);
                    jQuery('.response-div-' + div_index).find('.preloader-icon').hide();
                    var current_div = jQuery('.response-div-' + div_index).find('.elementor-shortcode');
                    current_div.html(html); // Replace the current HTML content with the processed markdown
                }
            }
        };
        source.onerror = function (event) {
            div_index = 0;
            source.close();
            jQuery('.error_message').css('display', 'flex');
        };
    })
    .catch(error => {
        console.error("Error fetching user role:", error);
    });
