<?php
/**
 * Plugin Name:       Writify
 * Description:       Score IELTS Essays x GPT
 * Version:           1.2.9
 * Author:            IELTS Science
 * Copyright:         © 2023-2026 RLT
 */

// Define the plugin constants if not defined.
defined("OPAIGFRLT_URL") or define("OPAIGFRLT_URL", plugin_dir_url(__FILE__));
defined("OPAIGFRLT_PATH") or define("OPAIGFRLT_PATH", plugin_dir_path(__FILE__));
defined("OPAIGFRLT_LOG") or define("OPAIGFRLT_LOG", false);

require 'plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/bi1101/Writify-WP-plugin/',
    __FILE__,
    'writify-gform-openai'
);

//Set the branch that contains the stable release.
$myUpdateChecker->setBranch('main');

//Optional: If you're using a private repository, specify the access token like this:
//$myUpdateChecker->setAuthentication('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');

// Check if Gravity Forms is active
if (class_exists('GFForms')) {
    // Include the Parsedown library
    require_once plugin_dir_path(__FILE__) . 'Includes/Libraries/parsedown-1.7.4/Parsedown.php';

    // Include custom merge tag logic
    require_once plugin_dir_path(__FILE__) . 'Includes/merge tags/parsedown_merge_tag.php';
    require_once plugin_dir_path(__FILE__) . 'Includes/merge tags/band_score_merge_tag.php';
    require_once plugin_dir_path(__FILE__) . 'Includes/merge tags/overall_band_score_merge_tag.php';
    require_once plugin_dir_path(__FILE__) . 'Includes/merge tags/generated_band_score_merge_tag.php';
    require_once plugin_dir_path(__FILE__) . 'Includes/merge tags/word_count_merge_tag.php';
}

// Add turnitin index
require_once plugin_dir_path(__FILE__) . 'Includes/turnitin_index.php';

/**
 * This is for the form to redirect user to the result page immediately after submission, the default behavior is to process OpenAI feeds before redirection.
 * This code adds a filter to the "gform_gravityforms-openai_pre_process_feeds" hook.
 * The filter callback function "__return_empty_string" is used to return an empty string.
 * This effectively prevents any processing of feeds for the "gform_gravityforms-openai_pre_process_feeds" hook.
 */
add_filter("gform_gravityforms-openai_pre_process_feeds", '__return_empty_string');

/**
 * Retrieves the Open AI feeds associated with a specific form.
 *
 * @param int|null $form_id The ID of the form. If null, retrieves feeds for all forms.
 * @return array An array of feeds.
 */
function writify_get_feeds($form_id = null)
{
    global $wpdb;

    $form_filter = is_numeric($form_id)
        ? $wpdb->prepare("AND form_id=%d", absint($form_id))
        : "";

    $sql = $wpdb->prepare("SELECT * FROM {$wpdb->prefix}gf_addon_feed WHERE addon_slug=%s {$form_filter} ORDER BY `feed_order`, `id` ASC", "gravityforms-openai");

    $results = $wpdb->get_results($sql, ARRAY_A);
    foreach ($results as &$result) {
        $result["meta"] = json_decode($result["meta"], true);
    }

    return $results;
}

/**
 * Registers the REST route for the event stream openai.
 *
 * @return void
 */
function writify_register_routes()
{
    register_rest_route(
        'writify/v1',
        '/event_stream_openai/',
        array(
            'methods' => 'GET',
            'callback' => 'event_stream_openai',
            'permission_callback' => '__return_true',
            // If you want to restrict access, modify this
        )
    );
    
    // Register the save-pronun-error route
    register_rest_route(
        'writify/v1',
        '/save-pronun-error/',
        array(
            'methods' => 'POST',
            'callback' => 'save_pronun_error',
            'permission_callback' => '__return_true',
            'args' => array(
                'formId' => array(
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);  // Ensure formId is numeric
                    },
                ),
                'entryId' => array(
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);  // Ensure entryId is numeric
                    },
                ),
            ),
        )
    );
    // Register the save-fluency-errors route
    register_rest_route(
        'writify/v1',
        '/save-fluency-errors/',
        array(
            'methods' => 'POST',
            'callback' => 'save_fluency_errors',
            'permission_callback' => '__return_true',
            'args' => array(
                'formId' => array(
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);  // Ensure formId is numeric
                    },
                ),
                'entryId' => array(
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);  // Ensure entryId is numeric
                    },
                ),
            ),
        )
    );


    // Register the delete-pronun-error route
    register_rest_route(
        'writify/v1',
        '/delete-pronun-error/',
        array(
            'methods' => 'POST',
            'callback' => 'delete_pronun_error',
            'permission_callback' => '__return_true', 
            'args' => array(
                'formId' => array(
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);  // Ensure formId is numeric
                    },
                ),
                'entryId' => array(
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);  // Ensure entryId is numeric
                    },
                ),
                'errorId' => array(
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param);  // Ensure errorId is numeric
                    },
                ),
            ),
        )
    );
}
add_action('rest_api_init', 'writify_register_routes');
// Callback function for save-pronun-error
function save_pronun_error($data) {
    $formId = sanitize_text_field($data['formId']);
    $entryId = sanitize_text_field($data['entryId']);
    $pronunErrorObj = $data->get_param('pronunErrorObj'); // Get the sent pronunErrorObj
    $form = GFAPI::get_form($formId);
    $entry = GFAPI::get_entry($entryId);
    $GWiz_GF_OpenAI_Object = new GWiz_GF_OpenAI();
    $feeds = writify_get_feeds($formId);
    $pronunFeed = null;

    // Find the correct pronunciation feed
    foreach ($feeds as $feed) {
        if ($feed['meta']['endpoint'] == 'pronunciation') {
            $pronunFeed = $feed;
            break;
        }
    }

    // If we have a valid feed
    if ($pronunFeed) {
        $pronun_field_id = rgar($pronunFeed['meta'], 'pronunciation_map_result_to_field');
        
        // Get the stored formated response data for the entry
        $formatedResponse = gform_get_meta($entry['id'], "formated_pronunciation_response_".$feed['id']);
        
        // If no previous data, create an empty array
        if(!$formatedResponse){
            $formatedResponse = [];
        }

        // Add the new pronunciation error to the formatedResponse array
        $formatedResponse[] = $pronunErrorObj;

        // Update the meta with the new formatedResponse array
        gform_update_meta($entry['id'], 'formated_pronunciation_response_' . $feed['id'], $formatedResponse);

        // Initialize a new variable to store the human-readable content
        $newText = '';

        // Loop through each entry in the formatedResponse array and convert to human-readable format
        foreach ($formatedResponse as $response) {
            $humanReadable = sprintf(
                "Start: %s\nEnd: %s\nText: %s\nCorrect Pronunciation: %s\nPhonetic: %s\n\n",
                $response['start'],
                $response['end'],
                $response['text'],
                $response['correctPronunAudio'],
                $response['correctPhonetic']
            );
            $newText .= $humanReadable; // Append each formatted entry
        }

        // Save the updated human-readable text back to the entry field (clear field first)
        $entry[$pronun_field_id] = '';  // Clear the field
        $GWiz_GF_OpenAI_Object->maybe_save_result_to_field($pronunFeed, $entry, $form, $newText);  // Save the new text

        // Return success response with the updated text
        return rest_ensure_response(array(
            'status' => 'success',
            'message' => 'Pronunciation error saved',
            'text' => $newText,
        ));
    } else {
        return new WP_Error('feed_not_found', 'Pronunciation feed not found', array('status' => 404));
    }
}

// Callback function for save-fluency-errors
function save_fluency_errors($data) {
    $formId = sanitize_text_field($data['formId']);
    $entryId = sanitize_text_field($data['entryId']);
    $fluencyErrors = $data->get_param('fluencyErrors'); // Get the fluency errors array
    $form = GFAPI::get_form($formId);
    $entry = GFAPI::get_entry($entryId);
    $GWiz_GF_OpenAI_Object = new GWiz_GF_OpenAI();
    $feeds = writify_get_feeds($formId);
    $pronunFeed = null;

    // Find the correct pronunciation feed
    foreach ($feeds as $feed) {
        if ($feed['meta']['endpoint'] == 'pronunciation') {
            $pronunFeed = $feed;
            break;
        }
    }

    // If we have a valid feed
    if ($pronunFeed) {
        $fluency_field_id = rgar($pronunFeed['meta'], 'fluency_errors_field');

        // Update the meta with the new fluency response array
        gform_update_meta($entry['id'], 'fluency_errors_' . $feed['id'], $fluencyErrors);

        // Initialize a new variable to store the human-readable content
        $newText = '';

        // Loop through each fluency error in the array and convert to human-readable format
        foreach ($fluencyErrors as $error) {
            $humanReadable = sprintf(
                "Word Before Error Word: %s\nError Word: %s\nPause Error: %s\n\n",
                $error['previousWord'],
                $error['currentPronunWord'],
                $error['pauseError'],
            );
            $newText .= $humanReadable; // Append each formatted entry
        }

        // Save the updated human-readable text back to the entry field (clear field first)
        $entry[$fluency_field_id] = '';  // Clear the field
        if (!is_numeric($fluency_field_id)) {
			$GWiz_GF_OpenAI_Object->log_debug("No field mapped to save the Fluency Errors.");
		}
        $field = GFAPI::get_field($form, (int) $fluency_field_id);

        if (rgar($field, 'useRichTextEditor')) {
			$newText = wp_kses_post($newText); // Allow only certain HTML tags
		} else {
			// Convert <br> tags to line breaks
			if (!is_array($newText)) {
				$newText = htmlspecialchars_decode($newText); // Decode any HTML entities
				$newText = preg_replace('/<br\s*\/?>/i', "\n", $newText); // Convert <br> to \n
				$newText = wp_strip_all_tags($newText); // Remove all HTML tags
			}
		}
        $entry[$fluency_field_id] = $newText;
        $GWiz_GF_OpenAI_Object->log_debug("Processed text to save in field: " . $newText);
		$updated = GFAPI::update_entry_field($entry['id'], $fluency_field_id, $newText);

        $GWiz_GF_OpenAI_Object->log_debug("Fluency Field Updated:  " . $fluency_field_id . ", Successfull: " . print_r($updated,true));
        GFAPI::add_note(
            $entry["id"],
            0,
            "Fluency Errors: ",
            $newText
        );
        $GWiz_GF_OpenAI_Object->log_debug("Entry field updated. Field ID: " . $fluency_field_id . ", Text: " . $newText);
        gf_do_action(array('gf_openai_post_save_result_to_field', $form['id']), $newText);
        
        $saved_fluency_data = gform_get_meta($entry['id'], 'fluency_errors_' . $feed['id']);
        // Return success response with the updated text
        return rest_ensure_response(array(
            'status' => 'success',
            'message' => 'Fluency errors saved',
            'text' => $saved_fluency_data,
        ));
    } else {
        return new WP_Error('feed_not_found', 'Pronunciation feed not found', array('status' => 404));
    }
}

// Callback function for delete-pronun-error
function delete_pronun_error($data) {
    $formId = sanitize_text_field($data['formId']);
    $entryId = sanitize_text_field($data['entryId']);
    $errorId = sanitize_text_field($data['errorId']); // Get the error ID to delete

    $form = GFAPI::get_form($formId);
    $entry = GFAPI::get_entry($entryId);
    $GWiz_GF_OpenAI_Object = new GWiz_GF_OpenAI();
    $feeds = writify_get_feeds($formId);
    $pronunFeed = null;

    // Find the correct pronunciation feed
    foreach ($feeds as $feed) {
        if ($feed['meta']['endpoint'] == 'pronunciation') {
            $pronunFeed = $feed;
            break;
        }
    }

    // If we have a valid feed
    if ($pronunFeed) {
        $pronun_field_id = rgar($pronunFeed['meta'], 'pronunciation_map_result_to_field');

        // Get the stored formatedResponse from the meta
        $formatedResponse = gform_get_meta($entry['id'], "formated_pronunciation_response_".$feed['id']);

        // If the formatedResponse exists
        if ($formatedResponse) {
            // Find and remove the specific error based on errorId
            foreach ($formatedResponse as $index => $response) {
                if ($response['errorId'] == $errorId) {
                    unset($formatedResponse[$index]); // Remove the matching entry
                    break;
                }
            }

            // Re-index the array after removing the item
            $formatedResponse = array_values($formatedResponse);

            // Update the meta with the new formatedResponse array
            gform_update_meta($entry['id'], 'formated_pronunciation_response_' . $feed['id'], $formatedResponse);

            // Rebuild the human-readable text from the updated formatedResponse
            $updatedText = '';
            foreach ($formatedResponse as $response) {
                $humanReadable = sprintf(
                    "Start: %s\nEnd: %s\nText: %s\nCorrect Pronunciation: %s\nPhonetic: %s\n\n",
                    $response['start'],
                    $response['end'],
                    $response['text'],
                    $response['correctPronunAudio'],
                    $response['correctPhonetic']
                );
                $updatedText .= $humanReadable; // Append each formatted entry
            }

            // Save the updated text back to the entry field (clear and save new value)
            $entry[$pronun_field_id] = '';  // Clear the field
            $GWiz_GF_OpenAI_Object->maybe_save_result_to_field($pronunFeed, $entry, $form, $updatedText);

            return rest_ensure_response(array(
                'status' => 'success',
                'message' => "Error ID $errorId removed",
                'updatedText' => $updatedText,
            ));
        } else {
            return new WP_Error('no_errors_found', 'No pronunciation errors found to delete', array('status' => 404));
        }
    } else {
        return new WP_Error('feed_not_found', 'Pronunciation feed not found', array('status' => 404));
    }
}

add_action("wp_footer", "writify_enqueue_scripts_footer", 9999);

function writify_enqueue_scripts_footer()
{
    global $post;
    $slug = $post->post_name;
    $gf_speaking_result_page_id = 6;

    // Check if the page slug begins with "result" or "speaking-result"
    if (strpos($slug, 'result') !== 0 && strpos($slug, 'speaking-result') !== 0 || $post->ID == $gf_speaking_result_page_id) {
        return;
    }

    // Moved repeated code to a single function.
    $get_int_val = function ($key) {
        return isset($_GET[$key]) ? (int) sanitize_text_field($_GET[$key]) : 0;
    };

    $form_id = $get_int_val("form_id");
    $entry_id = $get_int_val("entry_id");
    $nonce = wp_create_nonce('wp_rest');
    // Instantiate GWiz_GF_OpenAI object and log the nonce
    $GWiz_GF_OpenAI_Object = new GWiz_GF_OpenAI();
    $GWiz_GF_OpenAI_Object->log_debug("Created nonce in footer: " . $nonce);
    ?>
    <script>
        var div_index = 0, div_index_str = '';
        var buffer = ""; // Buffer for holding messages
        var responseBuffer = '';
        var md = new Remarkable();

        const formId = <?php echo json_encode($form_id); ?>;
        const entryId = <?php echo json_encode($entry_id); ?>;
        // Include the nonce in the source URL
        const nonce = "<?php echo $nonce; ?>";
        const sourceUrl = `<?php echo rest_url(); ?>writify/v1/event_stream_openai?form_id=${formId}&entry_id=${entryId}&_wpnonce=${nonce}`
        console.log(nonce);
        const source = new EventSource(sourceUrl);
        source.addEventListener('message', handleEventStream);
        source.addEventListener('whisper', handleEventStream);
        source.addEventListener('chat/completions', handleEventStream);
        source.addEventListener('languagetool', handleEventStream);

        source.onerror = function (event) {
            div_index = 0;
            source.close();
            jQuery('.error_message').css('display', 'flex');
        };

        function handleEventStream(event) {
            if (event.data == "[ALLDONE]") {
                source.close();
            } else if (event.data.startsWith("[DIVINDEX-")) {
                // Clear the buffer
                buffer = "";
                div_index_str = event.data.replace("[DIVINDEX-", "").replace("]", "");
                div_index = parseInt(div_index_str);
                console.log(div_index);
                jQuery('.response-div-' + (div_index)).css('display', 'flex');
                jQuery('.response-div-divider' + (div_index)).show();
            } else if (event.data == "[DONE]") {
                // When a message is done, convert the buffer to HTML and display it
                var html = md.render(buffer);
                jQuery('.response-div-' + div_index).find('.preloader-icon').hide();
                var current_div = jQuery('.response-div-' + div_index).find('.e-con');
                current_div.html(html); // Replace the current HTML content with the processed markdown

                jQuery.when(current_div.html(html)).then(function () {
                    // Add the "upgrade_vocab" class to the <li> elements that match the format
                    addUpgradeVocabClass(current_div);
                });

                // Clear the buffer
                buffer = "";
            }
            else if (event.data.startsWith('{"response":')) {
                var jsonResponse = JSON.parse(event.data);

                if (jsonResponse.streamType === 'question') {
                    // Handling question stream in chunks
                    var questionChunk = jsonResponse.response;
                    if (questionChunk !== undefined) {
                        buffer += questionChunk;
                        var html = md.render(buffer);
                        var questionDiv = document.querySelector('.essay_prompt .elementor-widget-container');
                        questionDiv.innerHTML = html;
                    }
                } else {
                    // Handling my-text stream in chunks
                    var responseChunk = jsonResponse.response;
                    if (responseChunk !== undefined) {
                        buffer += responseChunk;
                        var html = md.render(buffer);
                        var myTextDiv = document.getElementById('my-text');
                        myTextDiv.innerHTML = html;
                    }
                }
            }
            else {
                // Add the message to the buffer
                var choices = JSON.parse(event.data).choices;
                if (choices[0].delta.content !== null) {
                    text = choices[0].delta.content;
                }
                if (text !== undefined) {
                    buffer += text;
                    // Convert the buffer to HTML and display it
                    var html = md.render(buffer);
                    jQuery('.response-div-' + div_index).find('.preloader-icon').hide();
                    var current_div = jQuery('.response-div-' + div_index).find('.e-con');
                    current_div.html(html); // Replace the current HTML content with the processed markdown
                }
            }
        }

    </script>
    <?php
}

/**
 * Enqueues necessary scripts and styles For Different Result Pages.
 *
 * @return void
 */
function writify_enqueue_scripts()
{
    // Get current post
    global $post;
    $gf_speaking_result_page_id = 6; // ID of Speaking Result Page For Gravity Forms
    // Initialize GF OPEN AI OBJECT
    $GWiz_GF_OpenAI_Object = new GWiz_GF_OpenAI();
    $settings = $GWiz_GF_OpenAI_Object->get_plugin_settings();

    // Check if we're inside a post
    if (is_a($post, 'WP_Post')) {
        $slug = $post->post_name;

        // General Scripts
        wp_enqueue_script('docx', 'https://unpkg.com/docx@8.0.0/build/index.js', array(), null, true);
        wp_enqueue_script('file-saver', 'https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/1.3.8/FileSaver.js', array(), null, true);
        wp_enqueue_script('remarkable', 'https://cdn.jsdelivr.net/remarkable/1.7.1/remarkable.min.js', array(), null, true);
        wp_enqueue_script('google-client', 'https://accounts.google.com/gsi/client', array(), null, true);
        wp_enqueue_script('google-api', 'https://apis.google.com/js/api.js?onload=onApiLoad', array(), null, true);


        // Enqueue scripts specifically for Speaking Result Page (based on page ID)
        if ($post->ID == $gf_speaking_result_page_id) {
            // Scripts for Speaking Result Page
            wp_enqueue_script('speaking-result-audio-player', plugin_dir_url(__FILE__) . 'Assets/js/result_audio_player.js', array('jquery'), time(), true);
            wp_enqueue_script('gf-result-speaking', plugin_dir_url(__FILE__) . 'Assets/js/gf_result_speaking.js', array('jquery','speaking-result-audio-player'), time(), true);
            wp_enqueue_style('speaking-result-audio-player', plugin_dir_url(__FILE__) . 'Assets/css/result_audio_player.css', array(), time(), 'all');
            wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css');
            wp_enqueue_script('gf-result-vocab-interaction-handler', plugin_dir_url(__FILE__) . 'Assets/js/gf_result_vocab_interaction_handler.js', array('jquery'), '1.0.0', true);
            wp_enqueue_script('gf-result-grammer-interaction-handler', plugin_dir_url(__FILE__) . 'Assets/js/gf_result_grammer_interaction_handler.js', array('jquery'), '1.0.0', true);
            wp_enqueue_script('gf-result-pronun-interaction-handler', plugin_dir_url(__FILE__) . 'Assets/js/gf_result_pronunciation_interaction_handler.js', array('jquery'), '1.0.0', true);

            // Localize scripts for Speaking Result Page
            wp_localize_script('gf-result-speaking', 'gfResultSpeaking', array(
                'formId' => isset($_GET['form_id']) ? (int) sanitize_text_field($_GET['form_id']) : 0,
                'entryId' => isset($_GET['entry_id']) ? (int) sanitize_text_field($_GET['entry_id']) : 0,
                'nonce' => wp_create_nonce('wp_rest'),
                'restUrl' => rest_url(),
            ));
            wp_localize_script('gf-result-pronun-interaction-handler', 'pronunData', array(
                'formId' => isset($_GET['form_id']) ? (int) sanitize_text_field($_GET['form_id']) : 0,
                'entryId' => isset($_GET['entry_id']) ? (int) sanitize_text_field($_GET['entry_id']) : 0,
                'nonce' => wp_create_nonce('pronun_api'),
                'restUrl' => rest_url(),
            ));

            // Enqueue additional scripts for DOCX export and Google Drive integration
            wp_enqueue_script('writify-docx-export', plugin_dir_url(__FILE__) . 'Assets/js/gf_docx_export_speaking-result.js', array('jquery'), time(), true);
            wp_enqueue_script('google-drive-integration', plugin_dir_url(__FILE__) . 'Assets/js/gf_google-drive-export-speaking-result.js', array('google-client', 'google-api', 'writify-docx-export'), time(), true);

        }
        // General scripts for pages starting with 'result'
        if (substr($slug, 0, 6) === 'result' && $post->ID != $gf_speaking_result_page_id) {
            wp_enqueue_script('writify-docx-export', plugin_dir_url(__FILE__) . 'Assets/js/docx_export.js', array('jquery'), '1.1.2', true);
            wp_enqueue_script('google-drive-integration', plugin_dir_url(__FILE__) . 'Assets/js/google-drive-export.js', array('google-client', 'google-api', 'writify-docx-export'), time(), true);
        }

        // Localize user data for DOCX export
        $current_user = wp_get_current_user();
        $primary_identifier = get_user_primary_identifier();
        $firstName = $current_user->user_firstname;
        $lastName = $current_user->user_lastname;
        if ($primary_identifier == 'No_membership' || $primary_identifier == 'subscriber' || $primary_identifier == 'Writify-plus' || $primary_identifier == 'plus_subscriber') {
            $lastName .= " from IELTS Science";
        }
        wp_localize_script('writify-docx-export', 'writifyUserData', array(
            'firstName' => $firstName,
            'lastName' => $lastName
        ));

        // Localize script for Google Drive integration
        $file_name = 'Result';
        if (isset($_GET['entry_id'])) {
            $file_name .= '-' . sanitize_text_field($_GET['entry_id']);
        }
        $file_name .= '-' . date('Y-m-d-His') . '.docx';
        wp_localize_script('google-drive-integration', 'driveData', array(
            'file_name' => $file_name,
            'api_key' => $settings['gcloud_console_api_key'],
            'client_id' => $settings['gcloud_app_client_id']
        ));

        // Enqueue result page styles
        wp_enqueue_style('result-page-styles', plugin_dir_url(__FILE__) . 'Assets/css/result_page_styles.css', array(), time());

        // Enqueue interaction handler script for non-speaking result pages (Existing Code Support)
        if (substr($slug, 0, 6) === 'result' && $post->ID != $gf_speaking_result_page_id) {
            wp_enqueue_script('vocab-interaction-handler', plugin_dir_url(__FILE__) . 'Assets/js/vocab_interaction_handler.js', array('jquery'), '1.0.0', true);
        }

        // Enqueue Grammarly and other interaction scripts for pages starting with 'speaking-result'
        if (substr($slug, 0, 15) === 'speaking-result') {
            wp_enqueue_script('grammarly-editor-sdk', 'https://js.grammarly.com/grammarly-editor-sdk@2.5?clientId=client_MpGXzibWoFirSMscGdJ4Pt&packageName=%40grammarly%2Feditor-sdk', array(), null, true);
            wp_enqueue_script('vocab-interaction-handler', plugin_dir_url(__FILE__) . 'Assets/js/vocab_interaction_handler.js', array('jquery'), '1.0.0', true);
        }
    }
}

add_action('wp_enqueue_scripts', 'writify_enqueue_scripts');

function google_drive_further_actions_shortcode() {
    ob_start();
    ?>
    <div class="popup" id="google-drive-popup">
        <div class="popup-content">
            <button class="close-popup" id="close-drive-popup">Close</button>
            <h3>Please enter the file name and select the Google Drive folder you want to save to.</h3>
            <div class="google-drive-form-container">
                <input type="text" id="file-name" placeholder="File Name">
                <button id="export-google-docs">Save to Google Drive</button>
            </div>
            <div class="google-drive-form-container">
                <a class="button btn file-saved-button" target="_blank" id="file-saved-button">File Saved See the file</a>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const openPopupButton = document.getElementById("open-drive-popup");
            const closePopupButton = document.getElementById("close-drive-popup");
            const popup = document.getElementById("google-drive-popup");

            openPopupButton.addEventListener("click", function () {
                popup.style.display = "flex";
            });

            closePopupButton.addEventListener("click", function () {
                popup.style.display = "none";
            });

            document.getElementById("export-google-docs").addEventListener("click", function (event) {
                event.preventDefault();
                handleAuthClick();
            });
        });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('google-drive-further-actions', 'google_drive_further_actions_shortcode');

/**
 * Makes a request to the OpenAI API for chat completions and Whisper and stream the reponse to the front end.
 *
 * @param array $feed The feed settings.
 * @param array $entry The entry id.
 * @param array $form The form id.
 * @param string $stream_to_frontend Whether to stream the response to the frontend.
 * @return void
 */
function writify_make_request($feed, $entry, $form, $stream_to_frontend)
{
    $GWiz_GF_OpenAI_Object = new GWiz_GF_OpenAI();
    $endpoint = $feed["meta"]["endpoint"];

    switch ($endpoint) {
        case "chat/completions":
            return writify_handle_chat_completions($GWiz_GF_OpenAI_Object, $feed, $entry, $form, $stream_to_frontend, $endpoint);
        case "whisper":
            return writify_handle_whisper_API($GWiz_GF_OpenAI_Object, $feed, $entry, $form);
        case 'languagetool':
            return writify_handle_languagetool($GWiz_GF_OpenAI_Object, $feed, $entry, $form);
        case 'pronunciation':
            return writify_handle_pronunciation($GWiz_GF_OpenAI_Object, $feed, $entry, $form);
    }
}

function writify_handle_chat_completions($GWiz_GF_OpenAI_Object, $feed, $entry, $form, $stream_to_frontend, $endpoint)
{
    // Identify the user role or membership title from the API request
    $primary_identifier = get_user_primary_identifier();
    // Log primary role or membership title for debugging
    $GWiz_GF_OpenAI_Object->log_debug("Primary identifier (role or membership): " . $primary_identifier);

    // Get the saved API base for the user role or membership from the feed settings
    $api_base = rgar($feed['meta'], "api_base_$primary_identifier", 'https://api.openai.com/v1/');

    // Log API base for debugging
    $GWiz_GF_OpenAI_Object->log_debug("API Base: " . $api_base);

    // Get the model and message from the feed settings
    if (strpos($api_base, 'predibase') !== false) {
        $model = $feed["meta"]['chat_completions_lora_adapter'];
        $message = $feed["meta"]["chat_completions_lorax_message"];
    } elseif (strpos($api_base, 'runpod') !== false || strpos($api_base, 'api3') !== false) {
        $model = $feed["meta"]['chat_completions_lora_adapter_HF'];
        $message = $feed["meta"]["chat_completions_lorax_message"];
        $pod_id = $feed["meta"]["runpod_pod_id"];
    } else {
        // Get the model from feed metadata based on user's role or membership
        $model = $feed["meta"]["chat_completion_model_$primary_identifier"];
        $message = $feed["meta"]["chat_completions_message"];
    }
    // Retrieve the field ID for the image link and then get the URL from the entry
    $image_link_field_id = rgar($feed["meta"], 'gpt_4_vision_image_link');
    $image_link_json = rgar($entry, $image_link_field_id);

    // Decode the JSON string to extract the URL
    $image_links = json_decode($image_link_json, true);
    // Parse the merge tags in the message.
    $message = GFCommon::replace_variables($message, $form, $entry, false, false, false, "text");

    // Is Request is Made for Scores?
     // Possible Values 'no','grammer_scores','vocab_scores'
    $request_for_scores = isset($feed['meta']['request_is_for_scores']) ? $feed['meta']['request_is_for_scores'] : 'no'; // Possible Values 'no','grammer_scores','vocab_scores'
    GFAPI::add_note(
        $entry["id"],
        0,
        "OpenAI Request (" . $feed["meta"]["feed_name"] . ")",
        sprintf(
            __(
                "Sent request to OpenAI chat/completions endpoint.",
                "gravityforms-openai"
            )
        )
    );

    // translators: placeholders are the feed name, model, prompt
    $GWiz_GF_OpenAI_Object->log_debug(
        __METHOD__ .
        "(): " .
        sprintf(
            __(
                'Sent request to OpenAI. Feed: %1$s, Endpoint: chat, Model: %2$s, Message: %3$s',
                "gravityforms-openai"
            ),
            $feed["meta"]["feed_name"],
            $model,
            $message
        )
    );

    // Initialize content with only text
    $content = $message;

    // Check if the model is Vision
    if (strpos($model, 'vision') !== false) {
        // Prepare content with the text and all valid image URLs
        $content = array(array('type' => 'text', 'text' => $message));
        foreach ($image_links as $image_link) {
            if (!empty($image_link)) {
                $content[] = array('type' => 'image_url', 'image_url' => array('url' => $image_link));
            }
        }
    }

    // Create the request body
    $body = [
        "messages" => [
            [
                "role" => "user",
                "content" => $content,
            ],
        ],
        "model" => $model,
    ];

    $url = $api_base . $endpoint;

    if ($api_base === 'https://writify.openai.azure.com/openai/deployments/IELTS-Writify/') {
        $url .= '?api-version=2023-03-15-preview';
    }
    if (strpos($api_base, 'runpod') !== false) {
        //Replace `ROD_ID` with the actual pod ID
        $url = str_replace('POD_ID', $pod_id, $url);
    }


    if ((strpos($api_base, 'predibase') !== false || strpos($api_base, 'api3') !== false) && ($primary_identifier == 'No_membership' || $primary_identifier == 'subscriber')) {
        $body["max_tokens"] = 1000;
    } else {
        $body["max_tokens"] = (float) rgar(
            $feed["meta"],
            $endpoint . "_" . "max_tokens",
            $GWiz_GF_OpenAI_Object->default_settings["chat/completions"][
                "max_tokens"
            ]
        );
    }

    $body["temperature"] = (float) rgar(
        $feed["meta"],
        $endpoint . "_" . "temperature",
        $GWiz_GF_OpenAI_Object->default_settings["chat/completions"][
            "temperature"
        ]
    );
    $body["top_p"] = (float) rgar(
        $feed["meta"],
        $endpoint . "_" . "top_p",
        $GWiz_GF_OpenAI_Object->default_settings["chat/completions"][
            "top_p"
        ]
    );
    $body["frequency_penalty"] = (float) rgar(
        $feed["meta"],
        $endpoint . "_" . "frequency_penalty",
        $GWiz_GF_OpenAI_Object->default_settings["chat/completions"][
            "frequency_penalty"
        ]
    );
    $body["presence_penalty"] = (float) rgar(
        $feed["meta"],
        $endpoint . "_" . "presence_penalty",
        $GWiz_GF_OpenAI_Object->default_settings["chat/completions"][
            "presence_penalty"
        ]
    );

    $body["stream"] = true;

    $timeout_duration = rgar($feed['meta'], $endpoint . '_' . 'timeout', 120);

    // Add retry mechanism
    $max_retries = 2;
    $retry_count = 0;

    do {
        $retry = false;

        // Regenerate headers before each retry
        $headers = $GWiz_GF_OpenAI_Object->get_headers($feed);

        // Set the new headers
        $header = [
            "Content-Type: " . $headers["Content-Type"],
            "Authorization: " . $headers["Authorization"],
            "api-key: " . $headers["api-key"]
        ];

        if (isset($headers['OpenAI-Organization'])) {
            $header[] = "OpenAI-Organization: " . $headers['OpenAI-Organization'];
        }

        $post_json = json_encode($body);
        $GWiz_GF_OpenAI_Object->log_debug("Post JSON: " . $post_json);
        $GWiz_GF_OpenAI_Object->log_debug("URL: " . $url);
        $GWiz_GF_OpenAI_Object->log_debug("Header: " . json_encode($header));
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_json);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout_duration);

        $object = new stdClass();
        $object->res = "";
        $object->error = "";

        $buffer = '';
        
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) use ($request_for_scores, $object, $stream_to_frontend, $GWiz_GF_OpenAI_Object, &$buffer) {
            $GWiz_GF_OpenAI_Object->log_debug("Raw data received: " . $data);
        
            // Append new data to the buffer
            $buffer .= $data;
        
            // Split the buffer into parts based on "data:"
            $pop_arr = explode("data:", $buffer);
        
            // Clear the buffer
            $buffer = '';
        
            foreach ($pop_arr as $pop_item) {
                $pop_item = trim($pop_item);
                if (empty($pop_item)) {
                    continue; // Skip this iteration if $pop_item is empty.
                }
                if (trim($pop_item) === '[DONE]') {
                    continue; // Skip this iteration and don't process or echo the [DONE] segment.
                }
        
                // Try to decode the JSON
                $pop_js = json_decode($pop_item, true);
        
                // If decoding fails, it means we have an incomplete JSON object
                if (json_last_error() !== JSON_ERROR_NONE) {
                    // Append the incomplete item back to the buffer
                    $buffer .= "data: " . $pop_item;
                    continue;
                }
        
                if (isset($pop_js["choices"])) {
                    $line = isset($pop_js["choices"][0]["delta"]["content"])
                        ? $pop_js["choices"][0]["delta"]["content"]
                        : "";
                    if ($line == "<s>") {
                        continue; // Skip this iteration if $line is equal to "<s>".
                    }
                    if (!empty($line) || $line == "1" || $line == "0") {
                        $object->res .= $line;
                    }
                } elseif (isset($pop_js['error'])) {
                    if (isset($pop_js['error']['message'])) {
                        $object->error = $pop_js['error']['message'];
                    }
                    if (isset($pop_js['error']['detail'])) {
                        $object->error = $pop_js['error']['detail'];
                    }
                }
        
                // Log the processed item
                $GWiz_GF_OpenAI_Object->log_debug("Processed item: " . json_encode($pop_js));
                    if($request_for_scores && $request_for_scores == 'no'){
                        if ($stream_to_frontend === 'yes') {
                            echo "event: " . 'chat/completions' . PHP_EOL;
                            echo "data: " . $pop_item . "\n\n";
                            flush();
                        }
                        if ($stream_to_frontend === 'question') {
                            if (!empty($line)) {
                                echo "event: " . 'chat/completions' . PHP_EOL;
                                echo "data: " . json_encode(['response' => $line, 'streamType' => 'question']) . "\n\n";
                                flush();
                            }
                        }
                        if ($stream_to_frontend === 'text') {
                            if (!empty($line)) { // Only send non-empty lines
                                echo "event: " . 'chat/completions' . PHP_EOL;
                                echo "data: " . json_encode(['response' => $line]) . "\n\n";
                            }
                            flush(); // Ensure the data is sent to the client immediately
                        }
                    }
            }

            return strlen($data);
        });

        curl_exec($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        //Log http status code
        $GWiz_GF_OpenAI_Object->log_debug("HTTP Status Code: " . $http_status);

        // Check and Log cURL Errors
        $curl_errno = curl_errno($ch);
        if ($curl_errno) {
            $error_msg = curl_error($ch);
            $GWiz_GF_OpenAI_Object->log_debug("cURL Error: " . $error_msg);
        }
        curl_close($ch);

        if (!empty($object->res)) {
            GFAPI::add_note(
                $entry["id"],
                0,
                "OpenAI Response (" . $feed["meta"]["feed_name"] . ")",
                $object->res
            );
            $entry = $GWiz_GF_OpenAI_Object->maybe_save_result_to_field(
                $feed,
                $entry,
                $form,
                $object->res
            );
            // Update the entry in the database
            $result = GFAPI::update_entry($entry);
            // Log the result of the entry update.
            if (is_wp_error($result)) {
                GFCommon::log_debug('Entry update failed' . ' Error: ' . $result->get_error_message());
            } else {
                GFCommon::log_debug('writify_update_post_advancedpostcreation(): Entry updated successfully');
            }
        } else {
            if ($http_status !== 200 || !empty($object->error) || $curl_errno === CURLE_OPERATION_TIMEDOUT) {
                $retry_count++;
                if ($retry_count <= $max_retries) {
                    $retry = true;
                    sleep(2); // Optional: add sleep time before retrying
                    $GWiz_GF_OpenAI_Object->add_feed_error(
                        $object->error,
                        $feed,
                        $entry,
                        $form
                    );
                    GFAPI::add_note(
                        $entry["id"],
                        0,
                        "Retrying after OpenAI Error Response (" . $feed["meta"]["feed_name"] . ")",
                        $object->error
                    );
                } else {
                    $GWiz_GF_OpenAI_Object->add_feed_error(
                        $object->error,
                        $feed,
                        $entry,
                        $form
                    );
                    GFAPI::add_note(
                        $entry["id"],
                        0,
                        "Stopped retry after OpenAI Error Response (" . $feed["meta"]["feed_name"] . ")",
                        $object->error
                    );
                    $GWiz_GF_OpenAI_Object->log_debug("Returning Object: " . print_r($object, true));
                    return $object;
                }
            }
        }
    } while ($retry);

    if($request_for_scores !== 'no'){
		$GWiz_GF_OpenAI_Object->log_debug("Request Is For Scores " . $request_for_scores);
		if (file_exists(plugin_dir_path(__FILE__) . 'Includes/merge tags/generated_band_score_merge_tag.php')) {
            require_once plugin_dir_path(__FILE__) . 'Includes/merge tags/generated_band_score_merge_tag.php';
        } else {
            $GWiz_GF_OpenAI_Object->log_debug("File Not Found");
        }
        if($request_for_scores == 'grammer_scores'){
            $criterion='GRA'; // LR for Lexical Resource (Vocabulary) GRA for Grammar
            $parsedData = parse_field_value($object->res);
            $GWiz_GF_OpenAI_Object->log_debug("Score Parsed Data: " . print_r($parsedData, true));
            error_log("Merged Field Values: " . print_r($parsedData, true));
            $word_count = str_word_count($object->res, 0, '0123456789');
            $grammer_score = get_lowest_band_score($parsedData, $criterion, $word_count);
            $GWiz_GF_OpenAI_Object->log_debug("Grammer Score: " . print_r($grammer_score, true));
            gform_add_meta(
                $entry["id"],
                "grammer_score",
                $grammer_score
            );
        }elseif($request_for_scores == 'vocab_scores'){
            $criterion='LR'; // LR for Lexical Resource (Vocabulary) GRA for Grammar
            $parsedData = parse_field_value($object->res);
            $GWiz_GF_OpenAI_Object->log_debug("Score Parsed Data: " . print_r($parsedData, true));
            error_log("Merged Field Values: " . print_r($parsedData, true));
            $word_count = str_word_count($object->res, 0, '0123456789');
            $vocab_score = get_lowest_band_score($parsedData, $criterion, $word_count);
            $GWiz_GF_OpenAI_Object->log_debug("Vocab Score: " . print_r($vocab_score, true));
            gform_add_meta(
                $entry["id"],
                "vocab_score",
                $vocab_score
            );
        }
    }
    gform_add_meta(
        $entry["id"],
        "openai_response_" . $feed["id"],
        $object->res
    );

    return $entry;

}

function writify_handle_whisper_API($GWiz_GF_OpenAI_Object, $feed, $entry, $form)
{
    $object = new stdClass();
    $object->res = "";
    $object->error = "";
    // Get file field ID, model, prompt, and language from the feed settings
    $model = rgar($feed['meta'], 'whisper_model', 'whisper-1');
    $file_field_id = rgar($feed['meta'], 'whisper_file_field');
    $prompt = rgar($feed['meta'], 'whisper_prompt', "I'm, uh, is a, you know, Vietnamese ESL student. So, like, uhm, I may make some mistake in my grammar.");
    $language = rgar($feed['meta'], 'whisper_language', 'en');

    // Logging the feed settings
    $GWiz_GF_OpenAI_Object->log_debug("Whisper feed settings: Model: {$model}, File Field ID: {$file_field_id}, Prompt: {$prompt}, Language: {$language}");

    // Get the file URLs from the entry (assuming it returns an array of URLs)
    $file_urls = rgar($entry, $file_field_id);
    $GWiz_GF_OpenAI_Object->log_debug("File URLs: " . print_r($file_urls, true));

    $combined_text = ""; // Initialize a string to store all transcriptions
    $combined_response = []; // Array of Raw Responses From Whisper

    // Decode JSON string to array if necessary
    if (is_string($file_urls)) {
        $file_urls = json_decode($file_urls, true);
        $GWiz_GF_OpenAI_Object->log_debug("Decoded file URLs: " . print_r($file_urls, true));
    }

    // Proceed only if $file_urls is an array
    if (is_array($file_urls)) {
        foreach ($file_urls as $file_url) {
            $GWiz_GF_OpenAI_Object->log_debug("Processing file URL: {$file_url}");
            $file_path = $GWiz_GF_OpenAI_Object->convert_url_to_path($file_url);
            $GWiz_GF_OpenAI_Object->log_debug("Converted file path: {$file_path}");

            if (is_readable($file_path)) {
                $curl_file = curl_file_create($file_path, 'audio/mpeg', basename($file_path));
                $body = array(
                    'file' => $curl_file,
                    'model' => 'systran/faster-whisper-medium.en',
                    'prompt' => $prompt,
                    'language' => $language,
                    'response_format' => 'verbose_json',
                    'timestamp_granularities[]' => ['word', 'segment']
                );
                $GWiz_GF_OpenAI_Object->log_debug("Request body for Whisper API: " . print_r($body, true));

                GFAPI::add_note(
                    $entry["id"],
                    0,
                    "OpenAI Request (" . $feed["meta"]["feed_name"] . ")",
                    sprintf(
                        __(
                            "Sent request to OpenAI audio/transcription endpoint.",
                            "gravityforms-openai"
                        )
                    )
                );

                $response = $GWiz_GF_OpenAI_Object->make_request('audio/transcriptions', $body, $feed);
                $GWiz_GF_OpenAI_Object->log_debug("Response from Whisper API: " . print_r($response, true));


                if (is_wp_error($response)) {
                    $GWiz_GF_OpenAI_Object->log_debug("Error from Whisper API: " . $response->get_error_message());
                    $GWiz_GF_OpenAI_Object->add_feed_error($response->get_error_message(), $feed, $entry, $form);
                    $object->error = $response->get_error_message();
                    return $object;
                } else if (rgar($response, 'error')) {
                    $GWiz_GF_OpenAI_Object->log_debug("Error in response data: " . $response['error']['message']);
                    $GWiz_GF_OpenAI_Object->add_feed_error($response['error']['message'], $feed, $entry, $form);
                    $object->error = $response['error']['message'];
                    return $object;
                } else {
                    // $text = $GWiz_GF_OpenAI_Object->get_text_from_response($response);
                    $text = $response['text'];
                    $GWiz_GF_OpenAI_Object->log_debug("Transcription text: {$text}");
                    if (!is_wp_error($text)) {
                        GFAPI::add_note($entry['id'], 0, 'Whisper API Response (' . $feed['meta']['feed_name'] . ')', $text);
                        // Append each transcription to the combined string
                        $combined_text .= $text . "\n\n";
                        $combined_response[] = $response;
                        // Stream each response using SSE
                        echo "event: " . 'whisper' . PHP_EOL;
                        echo "data: " . json_encode(['response' => $response]) . "\n\n";
                        flush(); // Flush data to the browser after each file is transcribed
                    } else {
                        $GWiz_GF_OpenAI_Object->log_debug("Error in extracting text: " . $text->get_error_message());
                        $GWiz_GF_OpenAI_Object->add_feed_error($text->get_error_message(), $feed, $entry, $form);
                    }
                }
            } else {
                $GWiz_GF_OpenAI_Object->log_debug("File not accessible: {$file_path}");
                $GWiz_GF_OpenAI_Object->add_feed_error("File is not accessible or does not exist: " . $file_path, $feed, $entry, $form);
                $object->error = "File not accessible: {$file_path}";
                return $object;
            }
        }
    } else {
        $GWiz_GF_OpenAI_Object->log_debug("file_urls is not an array.");
    }

    // Logging the combined text
    $GWiz_GF_OpenAI_Object->log_debug("Combined transcription text: {$combined_text}");

    // Update the entry with the combined transcriptions
    if (!empty($combined_text)) {
        GFAPI::add_note($entry['id'], 0, 'Whisper API Combined Response', $combined_text);
        $entry = $GWiz_GF_OpenAI_Object->maybe_save_result_to_field($feed, $entry, $form, $combined_text);

        // Optionally, store the combined text as a meta for the entry
        gform_add_meta($entry['id'], 'whisper_combined_response', json_encode($combined_response));
        gform_add_meta($entry['id'], 'audio_urls', $file_urls);

        // Store WPM
        $wpm = calculateWPM($combined_response);
        $GWiz_GF_OpenAI_Object->log_debug("whisper combined:" . print_r($combined_response,true));
        $GWiz_GF_OpenAI_Object->log_debug("WPM: {$wpm}");
        gform_add_meta($entry['id'], 'wpm', $wpm);
    }

    return $entry;

}

function writify_handle_languagetool($GWiz_GF_OpenAI_Object, $feed, $entry, $form)
{
    $object = new stdClass();
    $object->error = "";
    // Helper Function 
    function convert_to_human_readable($response_body)
	{
        if(count($response_body['matches']) < 1){
            $readable_text = "No Grammer Errors Found";
            return $readable_text;
        }

        $readable_text = "LanguageTool API found the following issues:\n";
        foreach ($response_body['matches'] as $match) {
            $message = $match['message'];
            $context = $match['context']['text'];
            $offset = $match['context']['offset'];
            $length = $match['context']['length'];
            $replacements = array_column($match['replacements'], 'value');
            $replacements_text = implode(', ', $replacements);

            $readable_text .= "\nIssue: $message\n";
            $readable_text .= "Context: " . substr($context, 0, $offset) . '[' . substr($context, $offset, $length) . ']' . substr($context, $offset + $length) . "\n";
            $readable_text .= "Suggested Replacements: $replacements_text\n";
        }
    
        return $readable_text;
	}
    // Prepare Payload
    $text_field_id = rgar($feed['meta'], 'languagetool_text_source_field');
    $text = rgar($entry, $text_field_id);
    $language = rgar($feed['meta'], 'languagetool__language', 'en-US');
    $enabled_only = rgar($feed['meta'], 'languagetool__enabled_only', 'false');
    $level = rgar($feed['meta'], 'languagetool__level', 'default');
    $disabled_categories = rgar($feed['meta'], 'languagetool__disabled_categories', '');

    $body = array(
        'text' => $text,
        'language' => $language,
        'enabledOnly' => $enabled_only,
        'level' => $level,
        'disabledCategories' => $disabled_categories
    );

    // Log the request body
    $GWiz_GF_OpenAI_Object->log_debug("Request body for LanguageTool API: " . print_r($body, true));

    // Send Request
    $response = $GWiz_GF_OpenAI_Object->make_request('languagetool', $body, $feed);
    $GWiz_GF_OpenAI_Object->log_debug("Response from LanguageTool API: " . print_r($response, true));

    if (is_wp_error($response)) {
        $GWiz_GF_OpenAI_Object->log_debug("Error from LanguageTool API: " . $response->get_error_message());
        $GWiz_GF_OpenAI_Object->add_feed_error($response->get_error_message(), $feed, $entry, $form);
        $object->error = $response->get_error_message();
        return $object;
    } else if (rgar($response, 'error')) {
        $GWiz_GF_OpenAI_Object->log_debug("Error in response data: " . $response['error']['message']);
        $GWiz_GF_OpenAI_Object->add_feed_error($response['error']['message'], $feed, $entry, $form);
        $object->error = $response['error']['message'];
        return $object;
    } else {
        $response_body = $response['body']; // Assuming response body contains the relevant data
        $human_readable_response_body = json_decode(wp_remote_retrieve_body($response), true);
        GFAPI::add_note(
            $entry['id'],
            0,
            'LanguageTool API Response (' . $feed['meta']['feed_name'] . ')',
            $response_body
        );
        // Optionally, store the response as a meta for the entry
        gform_add_meta($entry['id'], 'languagetool_response_' . $feed['id'], $response_body);
        
        // Convert the response into human-readable format and save
		$human_readable_response = convert_to_human_readable($human_readable_response_body);
        $entry = $GWiz_GF_OpenAI_Object->maybe_save_result_to_field($feed, $entry, $form, $human_readable_response);
        // Stream each response using SSE
        echo "event: " . 'languagetool' . PHP_EOL;
        echo "data: " . json_encode(['response' => $response_body]) . "\n\n";
        flush(); // Flush data to the browser after each file is processed
    }

    return $entry;
}

function writify_handle_pronunciation($GWiz_GF_OpenAI_Object, $feed, $entry, $form)
{
    
    $object = new stdClass();
    $object->res = "";
    $object->error = "";
    // Get field IDs and settings from the feed
    $text_field_id = rgar($feed['meta'], 'pronunciation_reference_text_field');
    $file_field_id = rgar($feed['meta'], 'pronunciation_audio_file_field');
    $user_allowed_to_use_api = false;
    $form_id = $form['id'];
    $requests_used_by_user = 0;
    $allowed_number_of_api_requests = 0;
    $is_user_logged_in = is_user_logged_in(  );

    $grading_system = rgar($feed['meta'], 'pronunciation_grading_system', 'HundredMark');
    $granularity = rgar($feed['meta'], 'pronunciation_granularity', 'Phoneme');
    $dimension = rgar($feed['meta'], 'pronunciation_dimension', 'Comprehensive');
    $enable_prosody = rgar($feed['meta'], 'pronunciation_enable_prosody', 'true');

    // Get the file URLs and reference text from the entry
    $file_urls = rgar($entry, $file_field_id);
    
    // Get Number of Allowed Requests Per User Role
    $allowed_requests_key = "pronunciation_requests_allowed_$user_role";
    $allowed_number_of_api_requests = !empty(rgar($feed['meta'], $allowed_requests_key)) ? rgar($feed['meta'], $allowed_requests_key) : -1;
    
    $gpls_result = check_user_submission_limit($GWiz_GF_OpenAI_Object, $feed, $entry, $form);
    $GWiz_GF_OpenAI_Object->log_debug("GPLS Test Result:".print_r($gpls_result,true));
    // Convert file_urls to array if it's a JSON string
    if (is_string($file_urls)) {
        $file_urls = json_decode($file_urls, true);
    }

    // Initialize an array to store responses
    $pronun_combined_response = array();
    $combined_pronun_fluency_scores = array(
        'fluency_scores' => [],
        'pronun_scores' => [],
    );
    $url_count = 0;
    // Proceed only if $file_urls is an array
    if (is_array($file_urls)) {
        foreach ($file_urls as $file_url) {
            $whisperResponses = gform_get_meta($entry['id'], 'whisper_combined_response');
            if($whisperResponses){
                $whisperResponses = json_decode($whisperResponses);
                if(is_array($whisperResponses) && count($whisperResponses) == count($file_urls)){
                    $currentWhisperResponse = $whisperResponses[$url_count];
                    $reference_text = $currentWhisperResponse->text;
                    // $GWiz_GF_OpenAI_Object->log_debug("Whisper Response Count " .count($whisperResponses));
                    // $GWiz_GF_OpenAI_Object->log_debug("File URLs " .print_r($file_urls, true));
                    // $GWiz_GF_OpenAI_Object->log_debug("Responses Is Array " .print_r($reference_text, true));
                }
            }
            $url_count++;
            $GWiz_GF_OpenAI_Object->log_debug("Processing file URL: {$file_url}");
            


            if($gpls_result['status'] === 'success'){
                // $file_url = 'https://beta.ieltsscience.fun/wp-content/uploads/2024/09/Describe-a-party-that-you-enjoyed-1716823970096.mp3';
                // $reference_text = "So, well, I don't really go to a lot of parties. And there aren't any memorable parties that I partake in because I think the reason is because my definition of party is kind of different. Because I often think that party often involves, like, a group of people, like a huge group of people just hanging out. And, like, in the now, we don't really have that kind of parties. We do have, we regularly hang out with our friends, just like a group of three guys. And we would just go to a restaurant or just go to a cafe, not regularly, regularly though. So, and obviously, I enjoyed, like, all of them. But there's, like, the most recent one is, like, yesterday where it's, like, my friend's birthday. And we just got together to his place and, you know, just have a good meal together and then it was pretty fun. Yeah. ";
                // Prepare the request body
                $body = array(
                    'url' => $file_url,
                    'reference_text' => $reference_text,
                    'grading_system' => $grading_system,
                    'granularity' => $granularity,
                    'dimension' => $dimension,
                    'enable_prosody' => $enable_prosody
                );
                $GWiz_GF_OpenAI_Object->log_debug("Request body for Pronunciation API: " . print_r($body, true));

                GFAPI::add_note(
                    $entry["id"],
                    0,
                    "OpenAI Request (" . $feed["meta"]["feed_name"] . ")",
                    sprintf(
                        __(
                            "Sent request to OpenAI pronunciation endpoint.",
                            "gravityforms-openai"
                        )
                    )
                );

                $response = $GWiz_GF_OpenAI_Object->make_request('pronunciation', $body, $feed);
                $GWiz_GF_OpenAI_Object->log_debug("Response from Pronunciation API: " . print_r($response, true));

                if (is_wp_error($response)) {
                    $GWiz_GF_OpenAI_Object->log_debug("Error from Pronunciation API: " . $response->get_error_message());
                    $GWiz_GF_OpenAI_Object->add_feed_error($response->get_error_message(), $feed, $entry, $form);
                    $object->error = $response->get_error_message();
                    return $object;
                } else if (rgar($response, 'error')) {
                    $GWiz_GF_OpenAI_Object->log_debug("Error in response data: " . $response['error']['message']);
                    $GWiz_GF_OpenAI_Object->add_feed_error($response['error']['message'], $feed, $entry, $form);
                    $object->error = $response->get_error_message();
                    return $response['error']['message'];
                } else {
                    $response_body = wp_remote_retrieve_body($response);
                    // $GWiz_GF_OpenAI_Object->log_debug("Pronunciation Response Body " . $response_body);
                    $response_body = $GWiz_GF_OpenAI_Object->parse_event_stream_data($response_body);
                    // $response_body = json_encode($response_body, JSON_PRETTY_PRINT);
                    $response_body = $response_body;
                    $GWiz_GF_OpenAI_Object->log_debug("Pretty JSON: " . print_r($response_body, true));
                    $pronun_combined_response[$file_url] = $response_body;
                    $GWiz_GF_OpenAI_Object->log_debug("Processed File URL: " . $file_url);
                    GFAPI::add_note(
                        $entry['id'],
                        0,
                        'Pronunciation API Response (' . $feed['meta']['feed_name'] . ')',
                        $response_body
                    );
                    $GWiz_GF_OpenAI_Object->log_debug("Before Saving Value in Entry Meta pronunciation_response_$url_count:".print_r($response_body));
                    // Optionally, store the response as a meta for the entry
                    gform_add_meta($entry['id'], 'pronunciation_response_' . $url_count, $response_body);
                    
                    // Calculate File Accuracy Score
                    $pronun_fluency_scores = calculate_pronun_fluency_scores($response_body);
                    $GWiz_GF_OpenAI_Object->log_debug("file_$url_count Pronunciation Score:".print_r($pronun_fluency_scores['pronun_score'],true));
                    $GWiz_GF_OpenAI_Object->log_debug("file_$url_count Fluency Score:".print_r($pronun_fluency_scores['fluency_score'],true));
                    $combined_pronun_fluency_scores['pronun_scores'][] = $pronun_fluency_scores['pronun_score'];
                    $combined_pronun_fluency_scores['fluency_scores'][] = $pronun_fluency_scores['fluency_score'];
                    // Update Current User Meta
                    $requests_used_by_user = get_user_meta($current_user->ID, "pronun_api_used_count_$form_id", true);
                    $requests_used_by_user = intval($requests_used_by_user) + 1;
                    update_user_meta( $current_user->ID, "pronun_api_used_count_$form_id", $requests_used_by_user);
                    // Stream each response using SSE
                    echo "event: " . 'pronunciation' . PHP_EOL;
                    echo "data: " . json_encode(['response' => $response_body]) . "\n\n";
                    flush(); // Flush data to the browser after each file is processed
                }
            }else{
                $response_body = array(
                    'error' => true,
                    'loggedIn' => $is_user_logged_in,
                    'message' => $gpls_result['message'],
                    'submission_count' => $gpls_result['submission_count'],
                    'submission_limit' => $gpls_result['submission_limit'],
                    'time_period' => $gpls_result['time_period'],
                );
                // Store the response as a meta for the entry
                gform_add_meta($entry['id'], 'pronunciation_response_' . $url_count, $response_body);

                // Stream each response using SSE
                echo "event: " . 'pronunciation' . PHP_EOL;
                echo "data: " . json_encode(['response' => $response_body]) . "\n\n";
                flush(); // Flush data to the browser after each file is processed
            }
        }
    } else {
        $GWiz_GF_OpenAI_Object->log_debug("file_urls is not an array.");
    }

    // Logging the combined responses
    // $GWiz_GF_OpenAI_Object->log_debug("Combined pronunciation responses: " . print_r($responses, true));

    // Update the entry with the combined responses
    if (!empty($pronun_combined_response)) {
        GFAPI::add_note($entry['id'], 0, 'Pronunciation API Combined Response', implode("\n\n", $pronun_combined_response));
    }

    function calculate_average($scores) {
        $count = count($scores);
        
        // Check if there are no elements in the array
        if ($count === 0) {
            return 0; // Return 0 if no elements
        }
        
        // If there is only one element, return that element as the average
        if ($count === 1) {
            return round($scores[0], 1);
        }
        
        // Otherwise, calculate the average and round to 1 decimal place
        $average = array_sum($scores) / $count;
        return round($average, 1);
    }
    
    // Calculate averages
    $avg_fluency_score = calculate_average($combined_pronun_fluency_scores['fluency_scores']);
    $avg_pronun_score = calculate_average($combined_pronun_fluency_scores['pronun_scores']);

    // Stream each response using SSE
    echo "event: " . 'fluency_score' . PHP_EOL;
    echo "data: " . json_encode(['response' => $avg_fluency_score]) . "\n\n";
    flush(); // Flush data to the browser after each file is processed
    // Stream each response using SSE
    echo "event: " . 'pronun_score' . PHP_EOL;
    echo "data: " . json_encode(['response' => $avg_pronun_score]) . "\n\n";
    flush(); // Flush data to the browser after each file is processed

    // Store the response as a meta for the entry
    gform_add_meta($entry['id'], 'fluency_score', $avg_fluency_score);
    gform_add_meta($entry['id'], 'pronun_score', $avg_pronun_score);

    return $entry;
}

function calculate_pronun_fluency_scores($pronunciation_api_parsed_response) {
    $sentence_count = count($pronunciation_api_parsed_response);

    // Initialize total scores
    $total_pronun_accuracy_scores = 0;
    $total_fluency_accuracy_scores = 0;

    // Loop through each sentence data and sum the accuracy and fluency scores
    foreach($pronunciation_api_parsed_response as $sentence_data) {
        // Scale down from 100 to 10
        $pronun_accuracy_score = $sentence_data['NBest'][0]['PronunciationAssessment']['AccuracyScore'] / 10;
        $fluency_accuracy_score = $sentence_data['NBest'][0]['PronunciationAssessment']['FluencyScore'] / 10;

        $total_pronun_accuracy_scores += $pronun_accuracy_score;
        $total_fluency_accuracy_scores += $fluency_accuracy_score;
    }

    // Calculate the average scores
    $pronun_score = $total_pronun_accuracy_scores / $sentence_count;
    $fluency_score = $total_fluency_accuracy_scores / $sentence_count;

    // Return the scores as an array
    return [
        'pronun_score' => round($pronun_score, 2),  // Rounded to 2 decimal places
        'fluency_score' => round($fluency_score, 2), // Rounded to 2 decimal places
    ];
}

function get_user_primary_identifier()
{
    $current_user = wp_get_current_user();

    // Default role/membership
    $primary_identifier = 'default';

    // Check for MemberPress memberships
    if (class_exists('MeprUser')) {
        $mepr_user = new MeprUser($current_user->ID);
        $active_memberships = $mepr_user->active_product_subscriptions();

        if (!empty($active_memberships)) {
            $primary_membership = get_post($active_memberships[0]);
            if ($primary_membership) {
                $primary_identifier = $primary_membership->post_name; // User has a membership
            }
        } else {
            $primary_identifier = 'No_membership'; // No active membership
        }
    } else if (!empty($current_user->roles)) {
        $primary_identifier = $current_user->roles[0]; // Fallback to user role
    }

    return $primary_identifier;
}

/**
 * Handles the event stream for OpenAI processing, this function is called when front end make Rest API call to the back end.
 *
 * @param WP_REST_Request $request The REST request object.
 * @return WP_Error|void Returns a WP_Error object if the nonce is invalid, otherwise void.
 */
function event_stream_openai(WP_REST_Request $request)
{
    $GWiz_GF_OpenAI_Object = new GWiz_GF_OpenAI();

    // New function to output SSE data.
    $send_data = function ($data,$event = 'message') {
        echo "event: " . $event . PHP_EOL;
        echo "data: " . $data . PHP_EOL;
        echo PHP_EOL;
        ob_flush();
        flush();
    };

    // Log the received nonce value
    $nonce = $request->get_param('_wpnonce');

    // Verify the nonce
    if (!wp_verify_nonce($nonce, 'wp_rest')) {
        return new WP_Error('forbidden', 'Invalid nonce', array('status' => 403));
    }

    if (!headers_sent()) {
        @ini_set("zlib.output_compression", 0);
        ob_implicit_flush(true);
        ob_end_flush();

        header("Content-Type: text/event-stream");
        header("Cache-Control: no-cache");

        $_slug = "gravityforms-openai";
        $form_id = isset($_GET["form_id"])
            ? (int) sanitize_text_field($_GET["form_id"])
            : 0;
        $entry_id = isset($_GET["entry_id"])
            ? (int) sanitize_text_field($_GET["entry_id"])
            : 0;
        if ($form_id < 1 || $entry_id < 1) {
            echo "data: [ALLDONE]" . PHP_EOL;
            echo PHP_EOL;
            flush();
        }

        $send_data("[DIVINDEX-0]");

        $feeds = writify_get_feeds($form_id);
        $send_data(json_encode($feeds),'feeds'); // temp
        if (empty($feeds)) {
            $send_data("[ALLDONE]");
        }

        $form = GFAPI::get_form($form_id);
        $entry = GFAPI::get_entry($entry_id);

        // Get current processed feeds.
        $meta = gform_get_meta($entry["id"], "processed_feeds");
        $send_data(json_encode($meta)); // temp
        // If no feeds have been processed for this entry, initialize the meta array.
        if (empty($meta)) {
            $meta = [];
        }

        $processed_feeds = isset($meta[$_slug]) ? $meta[$_slug] : "";
        if (!is_array($processed_feeds)) {
            $processed_feeds = [];
        }

        // Initialize a flag to check if any feeds were processed.
        $feeds_processed = false;

        // Loop through feeds.
        $feed_index = 1;
        foreach ($feeds as $feed) {
            $stream_to_frontend = rgar($feed['meta'], 'stream_to_frontend', 'yes');
            // Get the feed name.
            $feed_name = rgempty("feed_name", $feed["meta"])
                ? rgar($feed["meta"], "feedName")
                : rgar($feed["meta"], "feed_name");

            $end_point = isset($feed["meta"]["endpoint"])
                ? $feed["meta"]["endpoint"]
                : "";
            $field_id = isset(
                $feed["meta"][$end_point . "_map_result_to_field"]
            )
                ? $feed["meta"][$end_point . "_map_result_to_field"]
                : 0;

            if (empty($feed["is_active"])) {
                continue; // Skip this iteration if the feed is not active
            }

            if (in_array((string) $feed["id"], $processed_feeds)) {
                if ($end_point === "whisper") {
                    // Handle Whisper API response
                    // Send Audio URLs To Frontend First
                    $audio_urls = gform_get_meta($entry['id'], 'audio_urls');
                    $send_data(json_encode(['response' => $audio_urls]), 'audio_urls');
                    // Get WPM value If Stored
                    $wpm = gform_get_meta($entry['id'], 'wpm');
                    $send_data(json_encode(['response' => $wpm]), 'wpm');
                    // Handle More then One Responses Generated by More then One audio Files 
                    $whisperResponses = gform_get_meta($entry['id'], 'whisper_combined_response');
                    $whisperResponses = json_decode($whisperResponses);
                    if(is_array($whisperResponses)){
                        foreach($whisperResponses as $whisperResponse){
                            $send_data(json_encode(['response' => $whisperResponse]), $end_point);
                        }
                    }else{
                        $send_data(json_encode(['response' => $whisperResponses]), $end_point);
                    }
                }

                if ($end_point === "chat/completions") {
                    $request_for_scores = isset($feed['meta']['request_is_for_scores']) ? $feed['meta']['request_is_for_scores'] : 'no'; // Possible Values 'no','grammer_scores','vocab_scores'
					$GWiz_GF_OpenAI_Object->log_debug("Request For Scores: " . $request_for_scores);
                    if($request_for_scores !== 'no'){
                        if($request_for_scores == 'grammer_scores'){
                            $grammer_score = intVal(gform_get_meta($entry['id'], 'grammer_score')) ?? 0;
                            $send_data(json_encode(['response' => $grammer_score]), 'grammer_score');
                        }
                        if($request_for_scores == 'vocab_scores'){
                            $vocab_score = intVal(gform_get_meta($entry['id'], 'vocab_score')) ?? 0;
                            $send_data(json_encode(['response' => $vocab_score]), 'vocab_score');
                        }
                    }else{
                        $chat_completions = $entry[$field_id];
                        $send_data(json_encode(['response' => $chat_completions]), $end_point);
                    }
                }

                if ($end_point === "languagetool") {
                    $languagetool_response = gform_get_meta($entry['id'], 'languagetool_response_'. $feed["id"]);
                    $send_data(json_encode(['response' => $languagetool_response]) , $end_point);
                }

                if ($end_point === "pronunciation") {
                    $pronun_response = gform_get_meta($entry['id'], "formated_pronunciation_response_".$feed['id']);
                    $GWiz_GF_OpenAI_Object->log_debug("Meta Key " . print_r("formated_pronunciation_response_".$feed['id'],true));
                    $processedPronunResponse['saved_response'] = $pronun_response; // Adding a key saved_response to identify wheter it is a new response or saved response
                    $send_data(json_encode(['response' => $processedPronunResponse]), $end_point);
                    // Send Fluency Errors
                    $fluency_errors = gform_get_meta($entry['id'], 'fluency_errors_' . $feed['id']);
                    $send_data(json_encode(['response' => $fluency_errors]), 'fluency_errors');
                    $fluency_score = gform_get_meta($entry['id'], "fluency_score") ?? '';
                    // Stream each response using SSE
                    echo "event: " . 'fluency_score' . PHP_EOL;
                    echo "data: " . json_encode(['response' => $fluency_score]) . "\n\n";
                    flush(); // Flush data to the browser after each file is processed
                    $pronun_score = gform_get_meta($entry['id'], "pronun_score") ?? '';
                    // Stream each response using SSE
                    echo "event: " . 'pronun_score' . PHP_EOL;
                    echo "data: " . json_encode(['response' => $pronun_score]) . "\n\n";
                    flush(); // Flush data to the browser after each file is processed
                }

                if ($stream_to_frontend === 'yes') {
                    $lines = explode("<br />", $entry[$field_id]);
                    foreach ($lines as $line) {
                        $object = new stdClass();
                        if (empty(trim($line))) {
                            $line = "\r\n";
                        } else {
                            $line = trim($line) . "\r\n";
                        }
                        $object->content = $line;
                        $send_data(
                            json_encode(["choices" => [["delta" => $object]]])
                        );
                    }
                }
                if ($stream_to_frontend === 'yes' | $stream_to_frontend === 'question' | $stream_to_frontend === 'text') {
                    $send_data("[DONE]");
                    if ($stream_to_frontend === 'yes') {
                        $send_data("[DIVINDEX-" . $feed_index . "]");
                    }
                }
            } else {
                $send_data("[FIRST-TIME]");
                if($end_point == 'whisper'){
                    // Send File URLs to Frontend Before Sending Whisper Request.
                    $file_field_id = rgar($feed['meta'], 'whisper_file_field');
                    $file_urls = rgar($entry, $file_field_id);
                    $file_urls = json_decode($file_urls, true);
                    // $audio_urls = gform_get_meta($entry['id'], 'audio_urls');
                    $send_data(json_encode(['response' => $file_urls]), 'audio_urls');
                }
                // All requirements are met; process feed.

                $returned_entry = writify_make_request($feed, $entry, $form, $stream_to_frontend);
                // If returned value from the processed feed call is an array containing an id, set the entry to its value.
                if (is_array($returned_entry)) {
                    $GWiz_GF_OpenAI_Object->log_debug("Returned Entry After Request: " . print_r($returned_entry,true));
                    $entry = $returned_entry;
                    // Check if Entry Was for Getting Scores
                    $request_for_scores = isset($feed['meta']['request_is_for_scores']) ? $feed['meta']['request_is_for_scores'] : 'no'; // Possible Values 'no','grammer_scores','vocab_scores'
                    if($request_for_scores !== 'no'){
                        if($request_for_scores == 'grammer_scores'){
                            $grammer_score = intVal(gform_get_meta($entry['id'], 'grammer_score')) ?? 0;
                            $send_data(json_encode(['response' => $grammer_score]), 'grammer_score');
                        }
                        if($request_for_scores == 'vocab_scores'){
                            $vocab_score = intVal(gform_get_meta($entry['id'], 'vocab_score')) ?? 0;
                            $send_data(json_encode(['response' => $vocab_score]), 'vocab_score');
                        }
                    }
                    if($end_point == 'whisper'){
                        // Send WPM on Frontend
                        $wpm = gform_get_meta($entry['id'], 'wpm');
                        $send_data(json_encode(['response' => $wpm]), 'wpm');
                    }

                    // Adding this feed to the list of processed feeds
                    $processed_feeds[] = $feed["id"];
                    // // Update the processed_feeds metadata after each feed is processed
                    $meta[$_slug] = $processed_feeds;
                    gform_update_meta($entry["id"], "processed_feeds", $meta);
                }else{
                    // Send Error On Frontend
                    $send_data(json_encode(['error' => $returned_entry]), $end_point);
                }
                if ($stream_to_frontend === 'yes' | $stream_to_frontend === 'question' | $stream_to_frontend === 'text') {
                    $send_data("[DONE]");
                    if ($stream_to_frontend === 'yes') {
                        $send_data("[DIVINDEX-" . $feed_index . "]");
                    }
                }
                $feeds_processed = true; // Set flag to true if a feed is processed
            }
            if ($stream_to_frontend === 'yes') {
                $feed_index++;
            }
        }

        gform_update_meta($entry["id"], "{$_slug}_is_fulfilled", true);

        // After all feeds are processed
        if ($feeds_processed) {
            // Call function to update the post only if new feeds were processed
            writify_update_post_advancedpostcreation($form, $entry['id']);
        }

        $send_data("[ALLDONE]");
        die();
    }
    $send_data("[ALLDONE]");
    die();
}

function calculateWPM($whisperResponse) {
    $totalWpm = 0;
    $responseCount = 0;
    $wpm = 0;

    foreach ($whisperResponse as $responseObj) {
        $segments = $responseObj['segments'];
        $responseWpmTotal = 0;
        $segmentCount = 0;

        foreach ($segments as $segment) {
            $start = $segment['start'];
            $end = $segment['end'];
            $text = trim($segment['text']);

            $duration = $end - $start; // Duration in seconds
            $words = count(preg_split('/\s+/', $text)); // Count words
            $segmentWpm = ($words / ($duration / 60)); // Calculate WPM for the segment

            $responseWpmTotal += $segmentWpm;
            $segmentCount++;
        }

        if ($segmentCount > 0) {
            $totalWpm += ($responseWpmTotal / $segmentCount); // Average WPM per response
            $responseCount++;
        }
    }

    $wpm = $responseCount > 0 ? $totalWpm / $responseCount : 0; // Average WPM across all responses
    $wpm = floor($wpm);

    return $wpm;
}



/**
 * Updates a post using the Advanced Post Creation add-on after a Gravity Forms entry is finished processing by Open AI.
 *
 * @param array $form The form object.
 * @param int $entry_id The ID of the entry being updated.
 * @return void
 */
function writify_update_post_advancedpostcreation($form, $entry_id)
{
    if(!class_exists('GF_Advanced_Post_Creation')){
        return;
    }

    GFCommon::log_debug('writify_update_post_advancedpostcreation(): running');
    // Get the updated entry.
    $entry = GFAPI::get_entry($entry_id);

    // Get the instance of the APC add-on.
    $apc_addon = GF_Advanced_Post_Creation::get_instance();

    // Start logging.
    $apc_addon->log_debug(__METHOD__ . '(): Running update function for entry #' . $entry_id);

    // Retrieve the feeds for the form.
    $feeds = $apc_addon->get_feeds($form['id']);

    // Iterate over the feeds to find the post creation feed.
    foreach ($feeds as $feed) {
        // Check if the feed is for post creation.
        if ($feed['addon_slug'] === 'gravityformsadvancedpostcreation') {
            $apc_addon->log_debug(__METHOD__ . '(): Found post creation feed #' . $feed['id']);

            // Retrieve post ID from entry meta.
            $post_ids_meta = gform_get_meta($entry_id, $apc_addon->get_slug() . '_post_id');

            foreach ($post_ids_meta as $post_info) {
                if (isset($post_info['post_id']) && $post_info['feed_id'] == $feed['id']) {
                    // Found the correct feed and post ID, now update the post.
                    $post_id = $post_info['post_id'];
                    $apc_addon->log_debug(__METHOD__ . '(): Updating post #' . $post_id);
                    $apc_addon->update_post($post_id, $feed, $entry, $form);
                    break 2; // Exit both loops.
                }
            }
        }
    }
}

function writify_chatgpt_writelog($log_data)
{
    if (function_exists('error_log')) {
        error_log(date("Y-m-d H:i:s") . " - " . $log_data . PHP_EOL, 3, plugin_dir_path(__FILE__) . 'chatgpt_log.txt');
    }
}

// Add Audio Player Shortcode
add_shortcode( 'result-page-audio-player', 'render_result_page_audio_player');
function render_result_page_audio_player(){

    ob_start();
    ?>
    <div class="isfp-audio-player">
        <div class="isfp-controls-handler">
            <div class="backword isfp-vol-nav" onclick="backwardAudio(5)">
                <svg width="22" height="25" viewBox="0 0 22 25" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M10.8199 3.88655L12.2977 2.40875C12.4701 2.23635 12.5544 2.01569 12.5544 1.75536C12.5544 1.49504 12.4701 1.27438 12.2977 1.10198L12.2272 1.17254L12.2977 1.10198C12.1253 0.929597 11.9047 0.845312 11.6443 0.845312C11.384 0.845312 11.1634 0.929597 10.991 1.10198L10.991 1.10198L7.97815 4.1148C7.79012 4.30283 7.69305 4.52676 7.69305 4.78239C7.69305 5.03803 7.79012 5.26195 7.97815 5.44998L10.9625 8.43438C11.1506 8.62241 11.3745 8.71947 11.6301 8.71947C11.8858 8.71947 12.1097 8.62241 12.2977 8.43438C12.4858 8.24635 12.5828 8.02243 12.5828 7.76679C12.5828 7.51115 12.4858 7.28723 12.2977 7.0992L10.9904 5.79192H11.3743C13.7166 5.79192 15.6801 6.6105 17.2714 8.2486C18.8634 9.88744 19.6591 11.8755 19.6591 14.2188C19.6591 16.5611 18.8404 18.5489 17.2014 20.1879C15.5623 21.827 13.5746 22.6456 11.2322 22.6456C8.88985 22.6456 6.90213 21.827 5.26308 20.1879C3.62403 18.5489 2.80537 16.5611 2.80537 14.2188C2.80537 13.9483 2.71592 13.7184 2.53448 13.537C2.35305 13.3556 2.12316 13.2661 1.85268 13.2661C1.58221 13.2661 1.35232 13.3556 1.17089 13.537C0.989445 13.7184 0.9 13.9483 0.9 14.2188C0.9 15.6527 1.16774 16.9973 1.70389 18.2515C2.23932 19.504 2.97594 20.5996 3.91369 21.5373C4.85142 22.475 5.947 23.2117 7.1995 23.7471C8.45369 24.2832 9.79829 24.551 11.2322 24.551C12.6661 24.551 14.0107 24.2832 15.2649 23.7471C16.5174 23.2117 17.613 22.475 18.5507 21.5373C19.4885 20.5996 20.2251 19.504 20.7605 18.2515C21.2967 16.9973 21.5644 15.6527 21.5644 14.2188C21.5644 12.7848 21.2967 11.4402 20.7605 10.1861C20.2251 8.93355 19.4885 7.83797 18.5507 6.90024C17.613 5.96251 16.5174 5.22585 15.2649 4.69044C14.0107 4.15429 12.6661 3.88655 11.2322 3.88655H10.8199Z" fill="#26375F" stroke="#26375F" stroke-width="0.2"/>
                    <path d="M9.61621 15.2451L8.04395 14.8691L8.61133 9.82422H14.2031V11.417H10.2314L9.98535 13.625C10.1175 13.5475 10.318 13.4655 10.5869 13.3789C10.8558 13.2878 11.1566 13.2422 11.4893 13.2422C11.9723 13.2422 12.4007 13.3174 12.7744 13.4678C13.1481 13.6182 13.4648 13.8369 13.7246 14.124C13.9889 14.4111 14.1895 14.762 14.3262 15.1768C14.4629 15.5915 14.5312 16.0609 14.5312 16.585C14.5312 17.027 14.4629 17.4486 14.3262 17.8496C14.1895 18.2461 13.9821 18.6016 13.7041 18.916C13.4261 19.2259 13.0775 19.4697 12.6582 19.6475C12.2389 19.8252 11.7422 19.9141 11.168 19.9141C10.7396 19.9141 10.3249 19.8503 9.92383 19.7227C9.52734 19.5951 9.1696 19.4059 8.85059 19.1553C8.53613 18.9046 8.2832 18.6016 8.0918 18.2461C7.90495 17.8861 7.80697 17.4759 7.79785 17.0156H9.75293C9.78027 17.2982 9.85319 17.542 9.97168 17.7471C10.0947 17.9476 10.2565 18.1025 10.457 18.2119C10.6576 18.3213 10.8923 18.376 11.1611 18.376C11.4118 18.376 11.626 18.3281 11.8037 18.2324C11.9814 18.1367 12.125 18.0046 12.2344 17.8359C12.3438 17.6628 12.4235 17.4622 12.4736 17.2344C12.5283 17.002 12.5557 16.7513 12.5557 16.4824C12.5557 16.2135 12.5238 15.9697 12.46 15.751C12.3962 15.5322 12.2982 15.3431 12.166 15.1836C12.0339 15.0241 11.8652 14.901 11.6602 14.8145C11.4596 14.7279 11.2249 14.6846 10.9561 14.6846C10.5915 14.6846 10.3089 14.7415 10.1084 14.8555C9.91243 14.9694 9.74837 15.0993 9.61621 15.2451Z" fill="#26375F"/>
                </svg>
            </div>
            <div class="isfp-play-toggle" data-playing="false" data-audio="audio-1">
                <div class="isfp-play-icon">
                    <svg width="41" height="41" viewBox="0 0 41 41" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M20.4644 40.9453C31.5101 40.9453 40.4644 31.991 40.4644 20.9453C40.4644 9.89962 31.5101 0.945312 20.4644 0.945312C9.41866 0.945312 0.464355 9.89962 0.464355 20.9453C0.464355 31.991 9.41866 40.9453 20.4644 40.9453Z" fill="#26375F"/>
                        <path d="M16.5 14L26.5 20.5L16.5 27L16.5 14Z" fill="white"/>
                    </svg>
                </div>
                <div class="isfp-pause-icon">
                    <svg width="41" height="41" viewBox="0 0 41 41" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M20.4644 40.9453C31.5101 40.9453 40.4644 31.991 40.4644 20.9453C40.4644 9.89962 31.5101 0.945312 20.4644 0.945312C9.41866 0.945312 0.464355 9.89962 0.464355 20.9453C0.464355 31.991 9.41866 40.9453 20.4644 40.9453Z" fill="#26375F"/>
                        <path d="M16.4639 26.9453V14.9453" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M24.4644 26.9453V14.9453" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
            </div>
            <div class="forward isfp-vol-nav" onclick="forwardAudio(5)">
                <svg width="22" height="26" viewBox="0 0 22 26" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M11.1088 3.75667L9.63099 2.27886C9.45857 2.10647 9.37431 1.8858 9.37431 1.62548C9.37431 1.36516 9.45857 1.1445 9.63099 0.972098L9.70154 1.04266L9.63099 0.972096C9.8034 0.799714 10.024 0.71543 10.2844 0.71543C10.5447 0.71543 10.7653 0.799714 10.9377 0.972096L10.9377 0.972101L13.9506 3.98492C14.1386 4.17295 14.2357 4.39687 14.2357 4.65251C14.2357 4.90815 14.1386 5.13207 13.9506 5.3201L10.9662 8.3045C10.7781 8.49252 10.5542 8.58959 10.2986 8.58959C10.0429 8.58959 9.81901 8.49252 9.63099 8.3045C9.44296 8.11647 9.34589 7.89254 9.34589 7.63691C9.34589 7.38127 9.44296 7.15734 9.63099 6.96932L10.9383 5.66204H10.5544C8.21207 5.66204 6.24858 6.48062 4.65732 8.11871C3.06533 9.75756 2.26965 11.7456 2.26965 14.0889C2.26965 16.4313 3.08831 18.419 4.72736 20.058C6.36641 21.6971 8.35413 22.5157 10.6965 22.5157C13.0389 22.5157 15.0266 21.6971 16.6656 20.058C18.3047 18.419 19.1233 16.4313 19.1233 14.0889C19.1233 13.8184 19.2128 13.5885 19.3942 13.4071C19.5757 13.2257 19.8056 13.1362 20.076 13.1362C20.3465 13.1362 20.5764 13.2257 20.7578 13.4071C20.9393 13.5885 21.0287 13.8184 21.0287 14.0889C21.0287 15.5228 20.761 16.8674 20.2248 18.1216C19.6894 19.3741 18.9528 20.4697 18.015 21.4074C17.0773 22.3451 15.9817 23.0818 14.7292 23.6172C13.475 24.1533 12.1304 24.4211 10.6965 24.4211C9.26257 24.4211 7.91797 24.1533 6.66378 23.6172C5.41128 23.0818 4.3157 22.3451 3.37797 21.4074C2.44023 20.4697 1.70358 19.3741 1.16817 18.1216C0.63204 16.8674 0.364281 15.5228 0.364281 14.0889C0.364281 12.655 0.63204 11.3104 1.16817 10.0562C1.70358 8.80367 2.44023 7.70809 3.37797 6.77036C4.3157 5.83262 5.41128 5.09597 6.66378 4.56056C7.91797 4.02441 9.26257 3.75667 10.6965 3.75667H11.1088Z" fill="#26375F" stroke="#26375F" stroke-width="0.2"/>
                    <path d="M9.08008 15.375L7.50781 14.999L8.0752 9.9541H13.667V11.5469H9.69531L9.44922 13.7549C9.58138 13.6774 9.7819 13.5954 10.0508 13.5088C10.3197 13.4176 10.6204 13.3721 10.9531 13.3721C11.4362 13.3721 11.8646 13.4473 12.2383 13.5977C12.612 13.748 12.9287 13.9668 13.1885 14.2539C13.4528 14.541 13.6533 14.8919 13.79 15.3066C13.9268 15.7214 13.9951 16.1908 13.9951 16.7148C13.9951 17.1569 13.9268 17.5785 13.79 17.9795C13.6533 18.376 13.446 18.7314 13.168 19.0459C12.89 19.3558 12.5413 19.5996 12.1221 19.7773C11.7028 19.9551 11.2061 20.0439 10.6318 20.0439C10.2035 20.0439 9.78874 19.9801 9.3877 19.8525C8.99121 19.7249 8.63346 19.5358 8.31445 19.2852C8 19.0345 7.74707 18.7314 7.55566 18.376C7.36882 18.016 7.27083 17.6058 7.26172 17.1455H9.2168C9.24414 17.4281 9.31706 17.6719 9.43555 17.877C9.55859 18.0775 9.72038 18.2324 9.9209 18.3418C10.1214 18.4512 10.3561 18.5059 10.625 18.5059C10.8757 18.5059 11.0898 18.458 11.2676 18.3623C11.4453 18.2666 11.5889 18.1344 11.6982 17.9658C11.8076 17.7926 11.8874 17.5921 11.9375 17.3643C11.9922 17.1318 12.0195 16.8812 12.0195 16.6123C12.0195 16.3434 11.9876 16.0996 11.9238 15.8809C11.86 15.6621 11.762 15.473 11.6299 15.3135C11.4977 15.154 11.3291 15.0309 11.124 14.9443C10.9235 14.8577 10.6888 14.8145 10.4199 14.8145C10.0553 14.8145 9.77279 14.8714 9.57227 14.9854C9.3763 15.0993 9.21224 15.2292 9.08008 15.375Z" fill="#26375F"/>
                </svg>
            </div>
        </div>
        <div class="isfp-volumn-handler">
            <div class="volume">
                <input type="range" min="0" max="100" value="50" class="volume-range">
                <div class="icon">
                    <i class="fa fa-volume-up icon-size" aria-hidden="true"></i>
                </div>
                <div class="bar-hoverbox">
                    <div class="bar">
                        <div class="bar-fill"></div>
                    </div>
                </div>
            </div>
        </div>
        <audio id="audio-1" src=""></audio>
    </div>
    <?php
    return ob_get_clean();
}

function check_user_submission_limit( $GWiz_GF_OpenAI_Object, $feed, $entry, $form, $user_id = null ) {
    // Initialize result array
    $result = array(
        'status' => '',
        'message' => '',
        'submission_count' => 0,
        'submission_limit' => 0,
        'time_period' => ''
    );

    // Get current user ID if not provided
    if ( ! $user_id ) {
        $user_id = get_current_user_id();
    }
    $GWiz_GF_OpenAI_Object->log_debug("Step 1: User ID - " . print_r($user_id, true));

    // Get user role(s)
    $user = get_userdata( $user_id );
    $user_roles = $user->roles;
    $role = !empty($user_roles) ? $user_roles[0] : 'subscriber';  // Fallback to 'subscriber'
    $GWiz_GF_OpenAI_Object->log_debug("Step 2: User Role - " . print_r($role, true));

    // Access dynamically generated field values from $feed['meta']
    $time_period_value = rgar( $feed['meta'], "rule_time_period_value_$role", 1 );
    $time_period_unit = rgar( $feed['meta'], "rule_time_period_unit_$role", 'seconds' ); // Changed to seconds from your logs
    $time_period_type = rgar( $feed['meta'], "rule_limit_time_period_$role", 'day' );
    $calendar_period_value = rgar( $feed['meta'], "rule_calendar_period_$role", '' );
    $submission_limit = rgar( $feed['meta'], "rule_submission_limit_$role", 3 );
    
    $GWiz_GF_OpenAI_Object->log_debug("Step 3: Time Period and Submission Limit - Time Period Value: $time_period_value, Time Period Unit: $time_period_unit, Submission Limit: $submission_limit");

    // Check if the necessary classes exist
    if ( class_exists( 'GPLS_RuleTest' ) && class_exists('GPLS_Rule_User') ) {

        // Create GPLS_RuleTest instance
        $rule_test = new GPLS_RuleTest();
        $rule_test->form_id = rgar( $form, 'id', 1 );
        $GWiz_GF_OpenAI_Object->log_debug("Step 4: RuleTest Instance Created - Form ID: {$rule_test->form_id}");

        // Use the load method to set the user-specific rule
        $user_rule = GPLS_Rule_User::load( array( 'rule_user' => $user_id ) );
        $rule_test->rules[] = $user_rule;
        $GWiz_GF_OpenAI_Object->log_debug("Step 5: User Rule Loaded - Query Data: " . print_r($user_rule->query(), true));

        // Time period logic
        switch ( $time_period_type ) {
            case 'forever':
                $rule_test->time_period = array('type' => 'forever');
                $result['time_period'] = 'Forever';
                break;
            case 'calendar_period':
                $rule_test->time_period = array(
                    'type'  => 'calendar_period',
                    'value' => $calendar_period_value
                );
                $result['time_period'] = $calendar_period_value;
                break;
            case 'time_period':
                $rule_test->time_period = array(
                    'type'  => 'time_period',
                    'value' => $time_period_value,
                    'unit'  => $time_period_unit
                );
                $result['time_period'] = "$time_period_value $time_period_unit";
                break;
            default:
                $rule_test->time_period = array(
                    'type'  => 'day',
                    'value' => 1
                );
                $result['time_period'] = '1 day';
                break;
        }

        $GWiz_GF_OpenAI_Object->log_debug("Step 6: Time Period Configuration: " . print_r($rule_test->time_period, true));

        // Set the submission limit dynamically from the feed
        $rule_test->limit = $submission_limit;
        $GWiz_GF_OpenAI_Object->log_debug("Step 7: Submission Limit Set: " . $rule_test->limit);

        // Log rule test object before running
        $GWiz_GF_OpenAI_Object->log_debug("Before Rule Test Run: " . print_r($rule_test, true));

        // Run the rule test
        $rule_test->run();

        // Log rule test results after running
        $GWiz_GF_OpenAI_Object->log_debug("After Rule Test Run: Count - {$rule_test->count}, Failed - " . ($rule_test->failed() ? 'true' : 'false'));

        // Populate result with submission data
        $result['submission_count'] = $rule_test->count;
        $result['submission_limit'] = $rule_test->limit;

        // Check submission limit
        if ( $rule_test->failed() ) {
            $result['status'] = 'failed';
            $result['message'] = "Submission limit reached. The user has already submitted {$rule_test->count} entries within {$result['time_period']}.";
        } else {
            $result['status'] = 'success';
            $result['message'] = "The user has submitted {$rule_test->count} entries within {$result['time_period']}, under the limit of {$rule_test->limit}.";
        }
    } else {
        // Class does not exist
        $result['status'] = 'error';
        $result['message'] = 'Submission rule class not found.';
    }

    return $result;
}