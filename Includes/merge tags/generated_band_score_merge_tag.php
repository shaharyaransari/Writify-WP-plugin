<?php
add_filter('gform_custom_merge_tags', 'add_generated_band_score_merge_tags', 10, 4);
function add_generated_band_score_merge_tags($merge_tags, $form_id, $fields, $element_id)
{
    $criteria = ['TR', 'CC', 'LR', 'GRA', 'TA'];

    foreach ($criteria as $criterion) {
        $merge_tags[] = array(
            'label' => "Generated $criterion Band Score (Multiple Fields)",
            'tag' => "{generated_{$criterion}_band_score_[field_ids]}"
        );
    }

    return $merge_tags;
}
add_filter('gform_replace_merge_tags', 'replace_generated_band_score_merge_tags', 10, 7);
function replace_generated_band_score_merge_tags($text, $form, $entry, $url_encode, $esc_html, $nl2br, $format)
{
    $criteria = ['TR', 'CC', 'LR', 'GRA', 'TA'];

    foreach ($criteria as $criterion) {
        $custom_tag = "/{generated_{$criterion}_band_score_(.+?)}/";
        preg_match_all($custom_tag, $text, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $field_ids = explode('_', $match[1]);
            $allFieldValues = [];

            foreach ($field_ids as $field_id) {
                $field_value = rgar($entry, $field_id);
                $parsedData = parse_field_value($field_value);
                $allFieldValues = array_merge($allFieldValues, $parsedData);
            }
            error_log("Merged Field Values: " . print_r($allFieldValues, true));

            $band_score = get_lowest_band_score($allFieldValues, $criterion);
            $text = str_replace($match[0], $band_score, $text);
        }
    }

    return $text;
}


/**
 * Parses the field value and extracts relevant data to match to Band Descriptor.
 *
 * @param string $field_value The field value to be parsed.
 * @return array The parsed data extracted from the field value.
 */
function parse_field_value($field_value)
{
    // Normalize line breaks to a single type (\n)
    $normalized_value = str_replace(array("\r\n", "\r"), "\n", $field_value);

    // Split the normalized value into lines
    $lines = explode("\n", $normalized_value);
    $parsedData = [];

    foreach ($lines as $line) {
        // Check if line contains double quotes
        $startPos = strpos($line, '"');
        $endPos = strrpos($line, '"');

        if ($startPos !== false && $endPos !== false && $endPos > $startPos) {
            // Extract text within quotes
            $extractedText = substr($line, $startPos + 1, $endPos - $startPos - 1);
        } else {
            // Find the position of the colon
            $colonPos = strpos($line, ':');
            if ($colonPos !== false) {
                // Extract the text after the colon until the end of the line
                $extractedText = trim(substr($line, $colonPos + 1));
            } else {
                // If no colon or quotes, add the entire line
                $extractedText = trim($line);
            }
        }

        if ($extractedText !== '') {
            $parsedData[] = $extractedText;
        }
    }

    return $parsedData;
}


function get_band_descriptors($type)
{
    $descriptors = [
        'TR' => [
            9 => [
                'The prompt is appropriately addressed and explored in depth.',
                'Position is clear, fully developed, and directly answers the question/s.',
                'Ideas are relevant, fully extended, and well-supported.',
                'Extremely rare lapses in content or support.'
            ],
            8 => [
                'Fully and appropriately addresses the prompt.',
                'Position is clear, well-developed, and consistent.',
                'Ideas are relevant, extended, and well-supported.',
                'Occasional omissions or lapses in content.'
            ],
            7 => [
                'Appropriately addresses the main parts of the prompt.',
                'Position is clear and developed.',
                'Ideas extended and supported but may over generalise.',
                'May lack focus and precision in supporting ideas/material.'
            ],
            6 => [
                'Addresses the main parts, though some more than others, with appropriate format.',
                'Position is relevant but may lack clear conclusions.',
                'Main ideas are relevant but underdeveloped or unclear.'
            ],
            5 => [
                'Incompletely addresses main parts, sometimes with inappropriate format.',
                'Position expressed but not always clear.',
                'Main ideas are present but limited or not well-developed.',
                'Some details may be irrelevant, there may be some repetition.'
            ],
            4 => [
                'Minimal or tangential response, possibly due to misunderstanding, format may be inappropriate.',
                'Position discernible but not evident.',
                'Main ideas difficult to identify, lacking relevance or support.',
                'Some details may be irrelevant, there may be some repetition.'
            ],
            3 => [
                'No part of the prompt is adequately addressed, or the prompt has been misunderstood.',
                'No relevant position can be identified, little direct response to the question/s.',
                'Few ideas, and irrelevant or insufficiently developed.',
                'The prompt has been misunderstood.'
            ],
            2 => [
                'Content is barely related to the prompt.',
                'No position can be identified.',
                'May be glimpses of one or two ideas without development.'
            ]

        ],
        'CC' => [
            9 => [
                'The message can be followed effortlessly.',
                'Skillfully managed paragraphs.',
                'Cohesion rarely attracts attention with minimal or no lapses.'
            ],
            8 => [
                'Can be followed with ease, logically sequenced.',
                'Used sufficiently and appropriately, with logical idea sequencing in paragraphs.',
                'Well-managed cohesion with minor lapses.'
            ],
            7 => [
                'Logically organized, clear progression throughout, a few minor lapses may occur.',
                'Generally effective paragraph use, with mostly logical idea sequencing.',
                'Flexible use of cohesive devices with some inaccuracies or inappropriate amounts.'
            ],
            6 => [
                'Generally arranged coherently, clear overall progression.',
                'Sometimes illogical paragraph use, central topic may be unclear.',
                'Used to some good effect, but cohesion within and/or between sentences may be faulty or mechanical.'
            ],
            5 => [
                'Evident organization but not wholly logical, lacks overall progression.',
                'Inadequately used or missing paragraphs, unclear main topic.',
                'Limited or overused, with some inaccuracies. Inadequate and/or inaccurate use of reference and substitution, causing repetition.'
            ],
            4 => [
                'Not arranged coherently, no clear progression.',
                'No paragraphing, no clear main topic.',
                'Inaccurate and repetitive use of basic cohesive devices, lacks or inaccurate use of substitution and referencing.'
            ],
            3 => [
                'No apparent logical organization, ideas are discernible but difficult to relate.',
                'Unhelpful attempts at paragraphing.',
                'Minimal use of sequencers or cohesive devices, not necessarily indicate a logical relationship between ideas, cause difficulty in identifying referencing.'
            ]

        ],
        'LR' => [
            9 => [
                'Full flexibility and precise use of vocabulary are widely evident.',
                'A wide range of vocabulary, precise, natural, and sophisticated control of lexical features.',
                'Extremely rare errors in spelling and word formation, with minimal impact on communication.'
            ],
            8 => [
                'Uses a broad vocabulary fluently and flexibly for precise meaning.',
                'Skillfully uses uncommon or idiomatic language, despite occasional inaccuracies.',
                'Occasional errors in spelling and word formation, with minimal impact on communication.'
            ],
            7 => [
                'Sufficient vocabulary for some flexibility and precision.',
                'Some ability to use less common and/or idiomatic items with an awareness of style and collocation, though with some inappropriate choices.',
                'Few errors in spelling and word formation, do not detract from overall clarity.'
            ],
            6 => [
                'Generally adequate vocabulary for the task.',
                'Generally clear meaning despite limited range or lack of precision.',
                'Some errors in spelling and word formation, but do not impede communication.'
            ],
            5 => [
                'Limited but minimally adequate vocabulary for the task.',
                'Simple vocabulary used accurately, but lacks variation, may have frequent inappropriate choices.',
                'Noticeable errors in spelling and word formation, may cause difficulty for the reader.'
            ],
            4 => [
                'Very limited and inadequate vocabulary, basic and repetitive.',
                'Inappropriate use of memorized phrases or formulaic language.',
                'Errors in spelling and word formation may impede meaning.'
            ],
            3 => [
                'The resource is inadequate with possible over dependence on input material or memorised language.',
                'Very limited control of word choice, may severely impede meaning.',
                'Very limited control of spelling and word formation, errors predominate and may severely impede meaning.'
            ]

        ],
        'GRA' => [
            9 => [
                'Wide range of structures used with full flexibility and control.',
                'Appropriate grammar and punctuation throughout.'
            ],
            8 => [
                'Wide range of structures used flexibly and accurately.',
                'Mostly error-free sentences with occasional, minor errors that have minimal impact on communication.'
            ],
            7 => [
                'Variety of complex structures used with some flexibility and accuracy.',
                'Generally well-controlled grammar and punctuation with frequent error-free sentences; a few errors persist but do not impede communication.'
            ],
            6 => [
                'Mix of simple and complex forms, with limited flexibility. Less accuracy in complex structures.',
                'Errors in grammar and punctuation occur but rarely hinder communication.'
            ],
            5 => [
                'Limited range of structures, repetitive. Complex sentences attempted but often faulty.',
                'Frequent grammar errors that may cause difficulty for the reader, punctuation may be faulty.'
            ],
            4 => [
                'Very limited range of structures, mostly simple sentences, rare use of subordinate clauses.',
                'Some accurate structures, but frequent errors that may impede meaning. Punctuation is often faulty or inadequate.'
            ],
            3 => [
                'Attempted sentence forms, but errors in grammar and punctuation predominate.',
                'Errors in grammar and punctuation predominate, preventing most meaning from coming through.'
            ]

        ],
        'TA' => [
            9 => [
                'Clear overview with main trends, stages, or differences.',
                'Skillfully selected key features, clearly presented, highlighted and illustrated.',
                'Extremely rare lapses in content.'
            ],
            8 => [
                'Occasional omission or lapse in content.'
            ],
            7 => [
                'Covered and clearly highlighted key features, but could be more fully or appropriately illustrated or extended. Data are appropriately categorized.',
                'Relevant and accurate content with a few omissions or lapses.'
            ],
            6 => [
                'A relevant overview is attempted.',
                'Covered and adequately highlighted key features. Information is appropriately selected and supported using figures/data.',
                'Some irrelevant, inappropriate, or inaccurate details.'
            ],
            5 => [
                'Tend to focus on details without referring to the bigger picture.',
                'Not adequately covered key features, and mechanical recounting of details. No data to support the description.',
                'Include irrelevant, inappropriate or inaccurate material.'
            ],
            4 => [
                'Inappropriate format.',
                'Few key features have been selected.',
                'Irrelevant, repetitive, inaccurate or inappropriate key features.'
            ],
            3 => [
                'Largely irrelevant key features. Present limited and repetitive information.'
            ]
        ],
    ];
    return $descriptors[$type] ?? [];
}

function get_lowest_band_score($parsedData, $type)
{
    $bandDescriptors = get_band_descriptors($type);

    // Log the band descriptors
    //error_log("Band Descriptors for type $type: " . print_r($bandDescriptors, true));

    // Check if band descriptors are empty
    if (empty($bandDescriptors)) {
        //error_log("No band descriptors found for type $type");
        return '';
    }

    $lowestBand = max(array_keys($bandDescriptors));
    //error_log("Starting with highest band: $lowestBand");

    foreach ($parsedData as $value) {
        foreach ($bandDescriptors as $band => $descriptors) {
            if (in_array($value, $descriptors)) {
                $lowestBand = min($lowestBand, $band);
                break; // Stop checking once a match is found for this bullet point
            }
        }
    }

    return $lowestBand;
}

add_filter('gform_custom_merge_tags', 'add_bullet_points_with_scores_merge_tag', 10, 4);
function add_bullet_points_with_scores_merge_tag($merge_tags, $form_id, $fields, $element_id)
{
    $criteria = ['TR', 'CC', 'LR', 'GRA', 'TA'];

    foreach ($criteria as $criterion) {
        $merge_tags[] = array(
            'label' => "Bullet Points with Scores for $criterion",
            'tag' => "{bullet_points_with_scores_{$criterion}_[field_id]}"
        );
    }

    return $merge_tags;
}

add_filter('gform_replace_merge_tags', 'replace_bullet_points_with_scores_merge_tag', 10, 7);
function replace_bullet_points_with_scores_merge_tag($text, $form, $entry, $url_encode, $esc_html, $nl2br, $format)
{
    $criteria = ['TR', 'CC', 'LR', 'GRA', 'TA'];

    foreach ($criteria as $criterion) {
        $custom_tag = "/{bullet_points_with_scores_{$criterion}_(\d+)}/";
        preg_match_all($custom_tag, $text, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $field_id = $match[1];
            $field_value = rgar($entry, $field_id);

            // Process the field value to append scores to bullet points
            $processed_value = process_field_for_scores($field_value, $criterion);

            $text = str_replace($match[0], $processed_value, $text);
        }
    }

    return $text;
}


function process_field_for_scores($field_value, $type)
{
    $normalized_value = str_replace(array("\r\n", "\r"), "\n", $field_value);
    $lines = explode("\n", $normalized_value);
    $processedText = '';

    foreach ($lines as $line) {
        $extractedText = '';
        $startPos = strpos($line, '"');
        $endPos = strrpos($line, '"');
        $colonPos = strpos($line, ':');

        // Extract text within quotes
        if ($startPos !== false && $endPos !== false && $endPos > $startPos) {
            $extractedText = substr($line, $startPos + 1, $endPos - $startPos - 1);
        }
        // Extract text after the colon
        elseif ($colonPos !== false) {
            $extractedText = trim(substr($line, $colonPos + 1));
        }
        // Use the entire line if no quotes or colon
        else {
            $extractedText = trim($line);
        }

        // Process the extracted text
        if ($extractedText !== '') {
            $bandScore = get_band_score_for_text($extractedText, $type);
            if (!empty($bandScore)) {
                $processedText .= $line . ' - Characteristic of Band **' . $bandScore . "**\n";
            } else {
                $processedText .= $line . "\n";
            }
        }
    }

    return $processedText;
}

function get_band_score_for_text($text, $type)
{
    $bandDescriptors = get_band_descriptors($type);

    foreach ($bandDescriptors as $band => $descriptors) {
        if (in_array($text, $descriptors)) {
            return $band;
        }
    }
    return ''; // Default return if no match is found
}