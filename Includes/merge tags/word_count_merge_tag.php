<?php
function add_word_count_merge_tags($merge_tags, $form_id, $fields, $element_id) {
    $merge_tags[] = array('label' => 'Word Count', 'tag' => '{wordcount_[field_id]}');
    $merge_tags[] = array('label' => 'Word Count - Task 1', 'tag' => '{wordcount_task_1_[field_id]}');
    $merge_tags[] = array('label' => 'Word Count - Task 2', 'tag' => '{wordcount_task_2_[field_id]}');
    return $merge_tags;
}

//Hook the Custom Merge Tags into Gravity Forms
add_filter('gform_custom_merge_tags', 'add_word_count_merge_tags', 10, 4);

add_filter('gform_replace_merge_tags', 'replace_word_count_merge_tag', 10, 7);

function replace_word_count_merge_tag($text, $form, $entry, $url_encode, $esc_html, $nl2br, $format) {
    
	// Simple word count merge tag
    $matches_simple = array();
    preg_match_all('/{wordcount_(\d+)}/', $text, $matches_simple, PREG_SET_ORDER);
    
    foreach ($matches_simple as $match) {
        $field_id = isset($match[1]) ? $match[1] : 0;
        if (isset($entry[$field_id])) {
            $word_count = str_word_count($entry[$field_id], 0, '0123456789');
            $text = str_replace($match[0], $word_count, $text);
        }
    }
    
	// Task 1 merge tag
    $matches_task_1 = array();
    preg_match_all('/{wordcount_task_1_(\d+)}/', $text, $matches_task_1, PREG_SET_ORDER);
    
    foreach ($matches_task_1 as $match) {
        $field_id = isset($match[1]) ? $match[1] : 0;
        if (isset($entry[$field_id])) {
            $word_count = str_word_count($entry[$field_id], 0, '0123456789');
            if($word_count < 150) {
                $replacement = " - UNDER WORD";
            } else {
                $replacement = "";
            }
            $text = str_replace($match[0], $replacement, $text);
        }
    }

    // Task 2 merge tag
    $matches_task_2 = array();
    preg_match_all('/{wordcount_task_2_(\d+)}/', $text, $matches_task_2, PREG_SET_ORDER);
    
    foreach ($matches_task_2 as $match) {
        $field_id = isset($match[1]) ? $match[1] : 0;
        if (isset($entry[$field_id])) {
            $word_count = str_word_count($entry[$field_id], 0, '0123456789');
            if($word_count < 250) {
                $replacement = " - UNDER WORD";
            } else {
                $replacement = "";
            }
            $text = str_replace($match[0], $replacement, $text);
        }
    }
    
    return $text;
}
