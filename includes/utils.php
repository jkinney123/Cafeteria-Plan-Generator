<?php
if (!defined('ABSPATH'))
    exit;

function cpp_replace_tokens($html, $plan_id)
{
    $employer = get_post_meta($plan_id, '_cpp_employer', true);
    $restatement_effective_date = get_post_meta($plan_id, '_cpp_restatement_effective_date', true);
    $employer_address = get_post_meta($plan_id, '_cpp_employer_address', true);
    $claims_administrator = get_post_meta($plan_id, '_cpp_claims_administrator', true);
    $claims_administrator_address = get_post_meta($plan_id, '_cpp_claims_administrator_address', true);

    $tokens = [
        '{{employer}}' => $employer,
        '{{restatement_effective_date}}' => $restatement_effective_date,
        '{{employer_address}}' => $employer_address,
        '{{claims_administrator}}' => $claims_administrator,
        '{{claims_administrator_address}}' => $claims_administrator_address,
    ];

    return str_replace(array_keys($tokens), array_values($tokens), $html);
}

function cpp_sentence_split($text)
{
    // Basic sentence split (imperfect, but handles most English)
    $sentences = preg_split('/(?<=[.?!])\s+(?=[A-Z])/', trim($text), -1, PREG_SPLIT_NO_EMPTY);
    return $sentences;
}