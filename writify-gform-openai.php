<?php
/**
 * Plugin Name:       Writify
 * Description:       Score IELTS Essays x GPT
 * Version:           1.1.9
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
    // Define the add-on slug you are working with.
    $addon_slug = 'gravityforms-openai';

    // If form_id is null, it returns all feeds for the addon.
    $feeds = GFAPI::get_feeds(null, $form_id, $addon_slug);

    return $feeds;
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
}
add_action('rest_api_init', 'writify_register_routes');

add_action("wp_footer", "writify_enqueue_scripts_footer", 9999);
function writify_enqueue_scripts_footer()
{
    global $post;
    $slug = $post->post_name;

    // Check if the page slug begins with "result" or "speaking-result"
    if (strpos($slug, 'result') !== 0 && strpos($slug, 'speaking-result') !== 0) {
        return;
    }

    // Moved repeated code to a single function.
    $get_int_val = function ($key) {
        return isset ($_GET[$key]) ? (int) sanitize_text_field($_GET[$key]) : 0;
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
        const sourceUrl = `/wp-json/writify/v1/event_stream_openai?form_id=${formId}&entry_id=${entryId}&_wpnonce=${nonce}`;

        const source = new EventSource(sourceUrl);
        source.onmessage = function (event) {
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
                var current_div = jQuery('.response-div-' + div_index).find('.elementor-shortcode');
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

    </script>
    <?php
}

/**
 * Enqueues necessary scripts and styles based on the current post slug.
 *
 * @return void
 */
function writify_enqueue_scripts()
{
    // Get current post
    global $post;

    // Check if we're inside a post and get the slug
    if (is_a($post, 'WP_Post')) {
        $slug = $post->post_name;

        // Enqueue the script only if the slug starts with 'result'
        if (substr($slug, 0, 6) === 'result') {
            wp_enqueue_script('writify-docx-export', plugin_dir_url(__FILE__) . 'Assets/js/docx_export.js', array('jquery'), '1.0.2', true);
            // Enqueue Docx script
            wp_enqueue_script('docx', 'https://unpkg.com/docx@8.0.0/build/index.js', array(), null, true);
            // Enqueue FileSaver script
            wp_enqueue_script('file-saver', 'https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/1.3.8/FileSaver.js', array(), null, true);

            // Get current user's data
            $current_user = wp_get_current_user();
            $primary_identifier = get_user_primary_identifier();

            // Modify the user's name if they are a subscriber or have no membership
            $firstName = $current_user->user_firstname;
            $lastName = $current_user->user_lastname;
            if ($primary_identifier == 'No_membership' || $primary_identifier == 'subscriber' || $primary_identifier == 'Writify-plus') {
                $lastName .= " from IELTS Science"; // Add "from IELTS Science" to the last name
            }

            // Prepare data to pass to the script
            $data_to_pass = array(
                'firstName' => $firstName,
                'lastName' => $lastName
            );

            // Localize the script with the data
            wp_localize_script('writify-docx-export', 'writifyUserData', $data_to_pass);

            // Enqueue Remarkable Markdown Parser
            wp_enqueue_script('remarkable', 'https://cdn.jsdelivr.net/remarkable/1.7.1/remarkable.min.js', array(), null, true);

            // Enqueue Grammarly Editor SDK
            wp_enqueue_script('grammarly-editor-sdk', 'https://js.grammarly.com/grammarly-editor-sdk@2.5?clientId=client_MpGXzibWoFirSMscGdJ4Pt&packageName=%40grammarly%2Feditor-sdk', array(), null, true);

            // Enqueue the text interaction handler script
            wp_enqueue_script('vocab-interaction-handler', plugin_dir_url(__FILE__) . 'Assets/js/vocab_interaction_handler.js', array('jquery'), '1.0.0', true);

            // Enqueue the result page styles
            wp_enqueue_style('result-page-styles', plugin_dir_url(__FILE__) . 'Assets/css/result_page_styles.css', array(), '1.0.0');
        }

        // Enqueue the script only if the slug starts with 'speaking-result'
        if (substr($slug, 0, 15) === 'speaking-result') {
            // Enqueue necessary scripts
            wp_enqueue_script('writify-docx-export', plugin_dir_url(__FILE__) . 'Assets/js/docx_export_speaking.js', array('jquery'), '1.0.0', true);
            // Enqueue Docx script
            wp_enqueue_script('docx', 'https://unpkg.com/docx@8.0.0/build/index.js', array(), null, true);
            // Enqueue FileSaver script
            wp_enqueue_script('file-saver', 'https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/1.3.8/FileSaver.js', array(), null, true);

            // Get current user's data
            $current_user = wp_get_current_user();
            $primary_identifier = get_user_primary_identifier();

            // Modify the user's name if they are a subscriber or have no membership
            $firstName = $current_user->user_firstname;
            $lastName = $current_user->user_lastname;
            if ($primary_identifier == 'No_membership' || $primary_identifier == 'subscriber' || $primary_identifier == 'Writify-plus') {
                $lastName .= " from IELTS Science"; // Add "IELTS Science" to the last name
            }

            // Prepare data to pass to the script
            $data_to_pass = array(
                'firstName' => $firstName,
                'lastName' => $lastName
            );

            // Localize the script with the data
            wp_localize_script('writify-docx-export', 'writifyUserData', $data_to_pass);

            // Enqueue Remarkable Markdown Parser
            wp_enqueue_script('remarkable', 'https://cdn.jsdelivr.net/remarkable/1.7.1/remarkable.min.js', array(), null, true);

            // Enqueue Grammarly Editor SDK
            wp_enqueue_script('grammarly-editor-sdk', 'https://js.grammarly.com/grammarly-editor-sdk@2.5?clientId=client_MpGXzibWoFirSMscGdJ4Pt&packageName=%40grammarly%2Feditor-sdk', array(), null, true);

            // Enqueue the text interaction handler script
            wp_enqueue_script('vocab-interaction-handler', plugin_dir_url(__FILE__) . 'Assets/js/vocab_interaction_handler.js', array('jquery'), '1.0.0', true);

            // Enqueue the result page styles
            wp_enqueue_style('result-page-styles', plugin_dir_url(__FILE__) . 'Assets/css/result_page_styles.css', array(), '1.0.0');
        }
    }
}

add_action('wp_enqueue_scripts', 'writify_enqueue_scripts');


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
    }
}

function writify_handle_chat_completions($GWiz_GF_OpenAI_Object, $feed, $entry, $form, $stream_to_frontend, $endpoint)
{

    // Identify the user role or membership title from the API request
    $primary_identifier = get_user_primary_identifier();
    // Log primary role or membership title for debugging
    $GWiz_GF_OpenAI_Object->log_debug("Primary identifier (role or membership): " . $primary_identifier);

    // Get the saved API base for the user role or membership from the feed settings
    $option_name = 'api_base_' . $primary_identifier;
    $api_base = rgar($feed['meta'], $option_name, 'https://api.openai.com/v1/');

    // Log API base for debugging
    $GWiz_GF_OpenAI_Object->log_debug("API Base: " . $api_base);
    $model_option_name = 'chat_completion_model_' . $primary_identifier;

    // Get the model from feed metadata based on user's role or membership
    $model = rgar($feed["meta"], $model_option_name, 'gpt-3.5-turbo');
    $message = $feed["meta"]["chat_completions_message"];
    // Retrieve the field ID for the image link and then get the URL from the entry
    $image_link_field_id = rgar($feed["meta"], 'gpt_4_vision_image_link');
    $image_link_json = rgar($entry, $image_link_field_id);

    // Decode the JSON string to extract the URL
    $image_link_array = json_decode($image_link_json, true);
    $image_link = $image_link_array ? reset($image_link_array) : ''; // Get the first element of the array
    // Parse the merge tags in the message.
    $message = GFCommon::replace_variables($message, $form, $entry, false, false, false, "text");

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

    // Check if the model is GPT-4 Vision and if the image link is not empty
    if (strpos($model, 'vision') !== false && !empty($image_link)) {
        // Construct content with text and image URL
        $content = array(
            array('type' => 'text', 'text' => $message),
            array('type' => 'image_url', 'image_url' => array('url' => $image_link))
        );
    } else {
        // Construct content with only text
        $content = $message;
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

    $body["max_tokens"] = (float) rgar(
        $feed["meta"],
        $endpoint . "_" . "max_tokens",
        $GWiz_GF_OpenAI_Object->default_settings["chat/completions"][
            "max_tokens"
        ]
    );
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

    $timeout_duration = rgar($feed[0]['meta'], $endpoint . '_' . 'timeout', 120);

    // Add retry mechanism
    $max_retries = 20;
    $retry_count = 0;

    do {
        $retry = false;

        // Regenerate headers before each retry
        $headers = $GWiz_GF_OpenAI_Object->get_headers();

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

        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) use ($object, $stream_to_frontend) {
            $pop_arr = explode("data: ", $data);

            foreach ($pop_arr as $pop_item) {
                if (trim($pop_item) === '[DONE]') {
                    continue; // Skip this iteration and don't process or echo the [DONE] segment.
                }

                $pop_js = json_decode($pop_item, true);
                if (isset ($pop_js["choices"])) {
                    $line = isset ($pop_js["choices"][0]["delta"]["content"])
                        ? $pop_js["choices"][0]["delta"]["content"]
                        : "";
                    if (!empty ($line) || $line == "1" || $line == "0") {
                        $object->res .= $line;
                    }
                } elseif (isset ($pop_js['error'])) {
                    if (isset ($pop_js['error']['message'])) {
                        $object->error = $pop_js['error']['message'];
                    }
                    if (isset ($pop_js['error']['detail'])) {
                        $object->error = $pop_js['error']['detail'];
                    }
                }

                if ($stream_to_frontend === 'yes') {
                    echo "data: " . $pop_item . PHP_EOL;
                }
                if ($stream_to_frontend === 'question') {
                    if (!empty ($line)) {
                        echo "data: " . json_encode(['response' => $line, 'streamType' => 'question']) . "\n\n";
                    }
                }
                if ($stream_to_frontend === 'text') {
                    if (!empty ($line)) { // Only send non-empty lines
                        echo "data: " . json_encode(['response' => $line]) . "\n\n";
                    }
                    flush(); // Ensure the data is sent to the client immediately
                }
            }

            return strlen($data);
        });

        curl_exec($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

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
                    return $object;
                }
            }
        }
    } while ($retry);

    gform_add_meta(
        $entry["id"],
        "openai_response_" . $feed["id"],
        $object->res
    );

    return $entry;

}

function writify_handle_whisper_API($GWiz_GF_OpenAI_Object, $feed, $entry, $form)
{
    // Get file field ID, model, prompt, and language from the feed settings
    $model = rgar($feed['meta'], 'whisper_model', 'whisper-1');
    $file_field_id = rgar($feed['meta'], 'whisper_file_field');
    $prompt = rgar($feed['meta'], 'whisper_prompt', "A Vietnamese student is preparing for the IELTS speaking test. The speech may include parts 1, 2, or 3 of the exam, featuring a monologue where the student poses questions to themselves and then provides answers. Topics cover various aspects relevant to Vietnam, such as cultural landmarks, traditional foods, and significant historical figures. The student uses Vietnamese-specific terms where appropriate, showcasing cultural knowledge. Importantly, the speech includes intentional grammatical errors a non-native English speaker. The speech also includes natural speech patterns like 'uhm' and 'uh'. Describe a famous destination. Today, I want talking about. Umm, let me think like, hmm... Okay, here's what I'm, like, thinking...");
    $language = rgar($feed['meta'], 'whisper_language', 'en');

    // Logging the feed settings
    $GWiz_GF_OpenAI_Object->log_debug("Whisper feed settings: Model: {$model}, File Field ID: {$file_field_id}, Prompt: {$prompt}, Language: {$language}");

    // Get the file URLs from the entry (assuming it returns an array of URLs)
    $file_urls = rgar($entry, $file_field_id);
    $GWiz_GF_OpenAI_Object->log_debug("File URLs: " . print_r($file_urls, true));

    $combined_text = ""; // Initialize a string to store all transcriptions

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
                    'model' => $model,
                    'prompt' => $prompt,
                    'language' => $language
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
                } else if (rgar($response, 'error')) {
                    $GWiz_GF_OpenAI_Object->log_debug("Error in response data: " . $response['error']['message']);
                    $GWiz_GF_OpenAI_Object->add_feed_error($response['error']['message'], $feed, $entry, $form);
                } else {
                    $text = $GWiz_GF_OpenAI_Object->get_text_from_response($response);
                    $GWiz_GF_OpenAI_Object->log_debug("Transcription text: {$text}");
                    if (!is_wp_error($text)) {
                        GFAPI::add_note($entry['id'], 0, 'Whisper API Response (' . $feed['meta']['feed_name'] . ')', $text);
                        // Append each transcription to the combined string
                        $combined_text .= $text . "\n\n";

                        // Stream each response using SSE
                        echo "data: " . json_encode(['response' => $text]) . "\n\n";
                        flush(); // Flush data to the browser after each file is transcribed
                    } else {
                        $GWiz_GF_OpenAI_Object->log_debug("Error in extracting text: " . $text->get_error_message());
                        $GWiz_GF_OpenAI_Object->add_feed_error($text->get_error_message(), $feed, $entry, $form);
                    }
                }
            } else {
                $GWiz_GF_OpenAI_Object->log_debug("File not accessible: {$file_path}");
                $GWiz_GF_OpenAI_Object->add_feed_error("File is not accessible or does not exist: " . $file_path, $feed, $entry, $form);
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
        gform_add_meta($entry['id'], 'whisper_combined_response', $combined_text);
    }

    return $entry;

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
    $send_data = function ($data) {
        echo "data: " . $data . PHP_EOL;
        echo PHP_EOL;
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

        if (empty($feeds)) {
            $send_data("[ALLDONE]");
        }

        $form = GFAPI::get_form($form_id);
        $entry = GFAPI::get_entry($entry_id);

        // Get current processed feeds.
        $meta = gform_get_meta($entry["id"], "processed_feeds");

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
                    // Assuming $entry[$field_id] contains the Whisper response
                    $whisperResponse = $entry[$field_id];
                    $send_data(json_encode(['response' => $whisperResponse]));
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
                if ($stream_to_frontend === 'yes') {
                    $send_data("[DONE]");
                    $send_data("[DIVINDEX-" . $feed_index . "]");
                }
            } else {
                // All requirements are met; process feed.
                $returned_entry = writify_make_request($feed, $entry, $form, $stream_to_frontend);

                // If returned value from the processed feed call is an array containing an id, set the entry to its value.
                if (is_array($returned_entry)) {
                    $entry = $returned_entry;
                    // Adding this feed to the list of processed feeds
                    $processed_feeds[] = $feed["id"];

                    // Update the processed_feeds metadata after each feed is processed
                    $meta[$_slug] = $processed_feeds;
                    gform_update_meta($entry["id"], "processed_feeds", $meta);
                } else {
                    //skip
                }
                if ($stream_to_frontend === 'yes') {
                    $send_data("[DONE]");
                    $send_data("[DIVINDEX-" . $feed_index . "]");
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

/**
 * Updates a post using the Advanced Post Creation add-on after a Gravity Forms entry is finished processing by Open AI.
 *
 * @param array $form The form object.
 * @param int $entry_id The ID of the entry being updated.
 * @return void
 */
function writify_update_post_advancedpostcreation($form, $entry_id)
{

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