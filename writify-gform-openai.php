<?php
/**
 * Plugin Name:       Writify
 * Description:       Score IELTS Essays x GPT
 * Version:           1.0.3
 * Copyright: © 2023-2026 RLT
 */

// Define the plugin constants if not defined.
defined("OPAIGFRLT_URL") or define("OPAIGFRLT_URL", plugin_dir_url(__FILE__));
defined("OPAIGFRLT_PATH") or define("OPAIGFRLT_PATH", plugin_dir_path(__FILE__));
defined("OPAIGFRLT_LOG") or define("OPAIGFRLT_LOG", false);

add_filter("gform_gravityforms-openai_pre_process_feeds", '__return_empty_string');

function writify_get_feeds($form_id = null)
{
    global $wpdb;

    $form_filter = is_numeric($form_id)
        ? $wpdb->prepare("AND form_id=%d", absint($form_id))
        : "";

    $sql = $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}gf_addon_feed
        WHERE addon_slug=%s {$form_filter} ORDER BY `feed_order`, `id` ASC",
        "gravityforms-openai"
    );

    $results = $wpdb->get_results($sql, ARRAY_A);
    foreach ($results as &$result) {
        $result["meta"] = json_decode($result["meta"], true);
    }

    return $results;
}

function writify_register_routes()
{
    register_rest_route('writify/v1', '/event_stream_openai/', array(
        'methods' => 'GET',
        'callback' => 'event_stream_openai',
        'permission_callback' => '__return_true',
        // If you want to restrict access, modify this
    )
    );
}
add_action('rest_api_init', 'writify_register_routes');

add_action("wp_footer", "writify_ajax_calls", 9999);
function writify_ajax_calls()
{
    global $post;
    $slug = $post->post_name;

    // Check if the page slug begins with "result"
    if (strpos($slug, 'result') !== 0) {
        return;
    }

    // Moved repeated code to a single function.
    $get_int_val = function ($key) {
        return isset($_GET[$key]) ? (int) sanitize_text_field($_GET[$key]) : 0;
    };

    $form_id = $get_int_val("form_id");
    $entry_id = $get_int_val("entry_id");
    $nonce = wp_create_nonce('wp_rest');
    writify_chatgpt_writelog("Created nonce: " . $nonce);
    ?>
    <style>
        .elementor-shortcode {
            margin-top: 10px;
        }

        .elementor-shortcode li {
            padding-left: 5px;
        }

        .elementor-shortcode p {
            white-space: pre-wrap;
        }

        .upgrade_vocab {
            list-style: none;
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0px 20px -15px;
            box-shadow: 0px 4px 14px 0px rgba(0, 0, 0, 0.2);
        }

        span.original-vocab {
            color: #ffa600;
        }

        .upgrade_vocab:not(.expanded):hover {
            background: #F0F2FC;
            transition-duration: 0.2s;
            cursor: pointer;
        }

        span.short-explanation {
            color: #6D758D;
            font-weight: 300;
        }

        span.improved-vocab {
            background: #2551da;
            border-radius: 0.25rem;
            color: #FFFFFF;
            padding: 0.25rem 0.5rem;
            transition-duration: .2s;
        }

        span.improved-vocab:hover {
            background: #02289e;
            cursor: pointer;
        }

        mark {
            background-color: #ffa600;
            transition: opacity 0.2s;
            padding: 4px;
            border-radius: 8px;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/remarkable/1.7.1/remarkable.min.js"></script>
    <script
        src="https://js.grammarly.com/grammarly-editor-sdk@2.5?clientId=client_MpGXzibWoFirSMscGdJ4Pt&amp;packageName=%40grammarly%2Feditor-sdk"></script>
    <script>
        // Store frequently used selectors
        const $document = jQuery(document);
        const $myTextDiv = jQuery("#my-text");

        function formatText(text) {
            const format = /".*" -\> ".*"(\sor\s".*")?\nExplanation: .*/;
            if (!format.test(text)) return null;

            const explanation = text.match(/Explanation: (.*)/)[1];
            const firstSentence = explanation.match(/[^\.!\?]+[\.!\?]+/g)[0];
            const secondImprovedVocabMatch = text.match(/or "(.*)"/);
            let secondImprovedVocab = '';
            if (secondImprovedVocabMatch) {
                secondImprovedVocab = `<span class="or"> or </span><span class="improved-vocab">${secondImprovedVocabMatch[1]}</span>`;
            }

            return text.replace(/"(.*)" -\> "(.*?)"(\sor\s".*")?\n(Explanation: .*)/, `<span class="original-vocab">$1</span><span class="arrow">-\></span> <span class="improved-vocab">$2</span>${secondImprovedVocab}<span class="short-explanation"> · ${firstSentence}</span><br><span class="explanation">$4</span>`);
        }

        function createNewDivWithClass(html) {
            return jQuery('<div/>', {
                class: 'upgrade_vocab',
                html: html
            });
        }

        function hideAndShowElements($newDiv) {
            const $elementsToHide = $newDiv.find(".arrow, .or, .improved-vocab, .explanation");
            const $elementsToShow = $newDiv.find(".original-vocab, .short-explanation");
            $elementsToHide.hide();
            $elementsToShow.slideDown(200);
        }

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
                        const unhighlightedText = $myTextDiv.html().replace(/<mark>(.*?)<\/mark>/g, "$1");

                        // Escape any special characters in the original vocab
                        const escapedOriginalVocab = originalVocab.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");

                        // Highlight the occurrences of the original vocabulary in the content
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

        function addClickEventListenerToImprovedVocab($newDiv, updatedText) {
            $newDiv.find(".improved-vocab").on('click', function (event) {
                event.stopPropagation(); // Prevent the event from bubbling up to the document

                // Extract the original vocab from the updated text
                const originalVocabMatch = updatedText.match(/<span class="original-vocab">(.*?)<\/span><span class="arrow">-\><\/span>/);
                if (originalVocabMatch) {
                    const originalVocab = originalVocabMatch[1];

                    // Extract the improved vocab from the clicked element
                    const improvedVocab = jQuery(this).text();

                    // Get the text in the #my-text div and remove the <mark> tags
                    const myText = $myTextDiv.html();
                    const unmarkedText = myText.replace(/<mark>(.*?)<\/mark>/g, "$1");

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

    </script>
    <script>
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
                const userRole = data.role;

                // Now initiate the EventSource with the userRole in the query params
                const formId = <?php echo json_encode($form_id); ?>;
                const entryId = <?php echo json_encode($entry_id); ?>;
                const sourceUrl = `/wp-json/writify/v1/event_stream_openai?form_id=${formId}&entry_id=${entryId}&user_role=${userRole}`;

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
    </script>
    <?php
}

function writify_make_request($feed, $entry, $form)
{
    $GWiz_GF_OpenAI_Object = new GWiz_GF_OpenAI();

    $headers = $GWiz_GF_OpenAI_Object->get_headers();

    $endpoint = $feed["meta"]["endpoint"];

    if ($endpoint === "chat/completions") {
        $model = $feed["meta"]["chat_completions_model"];
        $message = $feed["meta"]["chat_completions_message"];

        // Parse the merge tags in the message.
        $message = GFCommon::replace_variables(
            $message,
            $form,
            $entry,
            false,
            false,
            false,
            "text"
        );

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

        $body = [
            "messages" => [
                [
                    "role" => "user",
                    "content" => $message,
                ],
            ],
            "model" => $model,
        ];

        // Identify the user role
        $current_user = wp_get_current_user();
        $user_roles = $current_user->roles;
        // Identify the user role from the API request
        $primary_role = isset($_REQUEST["user_role"]) ? sanitize_text_field($_REQUEST["user_role"]) : 'default';

        // Log primary role for debugging
        $GWiz_GF_OpenAI_Object->log_debug("Primary role: " . $primary_role);

        // Get the saved API base for the user role from the feed settings
        $option_name = 'api_base_' . $primary_role;
        $api_base = rgar($feed['meta'], $option_name, 'https://api.openai.com/v1/');

        // Log API base for debugging
        $GWiz_GF_OpenAI_Object->log_debug("API Base: " . $api_base);

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

        $header = [
            "Content-Type: " . $headers["Content-Type"],
            "Authorization: " . $headers["Authorization"],
            "api-key: " . $headers["api-key"]
        ];

        if (isset($headers['OpenAI-Organization'])) {
            $header[] = "OpenAI-Organization: " . $headers['OpenAI-Organization'];
        }

        // Add retry mechanism
        $max_retries = 150;
        $retry_count = 0;

        do {
            $retry = false;

            $post_json = json_encode($body);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_json);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

            $object = new stdClass();
            $object->res = "";
            $object->error = "";

            curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) use ($object) {
                $pop_arr = explode("data: ", $data);

                foreach ($pop_arr as $pop_item) {
                    if (trim($pop_item) === '[DONE]') {
                        continue; // Skip this iteration and don't process or echo the [DONE] segment.
                    }

                    $pop_js = json_decode($pop_item, true);
                    if (isset($pop_js["choices"])) {
                        $line = isset($pop_js["choices"][0]["delta"]["content"])
                            ? $pop_js["choices"][0]["delta"]["content"]
                            : "";
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

                    echo "data: " . $pop_item . PHP_EOL;
                }

                //writify_chatgpt_writelog(trim($data)); // Log the raw JSON

                return strlen($data);
            });

            curl_exec($ch);
            $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
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
                if (is_wp_error($result)) {
                    error_log('Failed to update entry: ' . $result->get_error_message());
                }

            } else {
                if ($http_status !== 200 || !empty($object->error)) {
                    $retry_count++;
                    if ($retry_count <= $max_retries) {
                        $retry = true;
                        sleep(2); // Optional: add sleep time before retrying
                        GFAPI::add_note(
                            $entry["id"],
                            0,
                            "OpenAI Error Response (" . $feed["meta"]["feed_name"] . ")",
                            $object->error
                        );
                        $GWiz_GF_OpenAI_Object->add_feed_error(
                            $object->error,
                            $feed,
                            $entry,
                            $form
                        );
                    } else {
                        GFAPI::add_note(
                            $entry["id"],
                            0,
                            "OpenAI Error Response (" . $feed["meta"]["feed_name"] . ")",
                            $object->error
                        );
                        $GWiz_GF_OpenAI_Object->add_feed_error(
                            $object->error,
                            $feed,
                            $entry,
                            $form
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
    } else {
        $object = new stdClass();
        $object->error = "Not allowed";
        return $object;
    }
}

add_action("wp_ajax_event_stream_openai", "event_stream_openai");
add_action("wp_ajax_nopriv_event_stream_openai", "event_stream_openai");
add_action('wp_ajax_writify_get_user_role', 'writify_get_user_role');
add_action('wp_ajax_nopriv_writify_get_user_role', 'writify_get_user_role');

function writify_get_user_role()
{
    $current_user = wp_get_current_user();
    $user_roles = $current_user->roles;
    $primary_role = !empty($user_roles) ? $user_roles[0] : 'default';

    echo json_encode(['role' => $primary_role]);
    wp_die();
}

function event_stream_openai(WP_REST_Request $request)
{

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

        // New function to output SSE data.
        $send_data = function ($data) {
            echo "data: " . $data . PHP_EOL;
            echo PHP_EOL;
            flush();
        };

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

            // If this feed is inactive, set a flag and continue to the next feed.
            if (!$feed["is_active"]) {
                $feeds_processed = true; // Mark that feeds were processed.
                continue;
            }

            if (in_array((string) $feed["id"], $processed_feeds)) {
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
                $send_data("[DONE]");
                $send_data("[DIVINDEX-" . $feed_index . "]");
            } else {
                //writify_chatgpt_writelog("Processing " . $feed_name . " is active!");

                // All requirements are met; process feed.
                $returned_entry = writify_make_request($feed, $entry, $form);

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
                $send_data("[DONE]");
                $send_data("[DIVINDEX-" . $feed_index . "]");
            }
            $feed_index++;
        }

        gform_update_meta($entry["id"], "{$_slug}_is_fulfilled", true);

        // If any feeds were processed, save the processed feed IDs.
        if (!empty($processed_feeds)) {
            // Add this Add-On's processed feeds to the entry meta.
            $meta[$_slug] = $processed_feeds;

            // Update the entry meta.
            gform_update_meta($entry["id"], "processed_feeds", $meta);
        }

        $send_data("[ALLDONE]");
        die();
    }
    $send_data("[ALLDONE]");
    die();
}
function writify_chatgpt_writelog($log_data)
{
    if (function_exists('error_log')) {
        error_log(date("Y-m-d H:i:s") . " - " . $log_data . PHP_EOL, 3, plugin_dir_path(__FILE__) . 'chatgpt_log.txt');
    }
}