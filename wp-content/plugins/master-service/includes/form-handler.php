<?php
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Handle form submission (hooked to admin_post[_nopriv]_mastter_service_submit)
 * Uses wp_mail, wp_handle_upload, and msttr_append_log.
 */
function msttr_handle_form_submission()
{
    // Only accept POST
    if (strtoupper($_SERVER['REQUEST_METHOD']) !== 'POST') {
        wp_send_json_error(['message' => 'Invalid request method.']);
        wp_die();
    }

    // Nonce check
    $nonce = isset($_POST['_mastter_service_nonce']) ? sanitize_text_field(wp_unslash($_POST['_mastter_service_nonce'])) : '';
    if (! wp_verify_nonce($nonce, 'mastter_service_nonce')) {
        wp_send_json_error(['message' => 'Security check failed (invalid nonce).']);
        wp_die();
    }

    // Required fields
    $required = ['client_name', 'email', 'phone', 'reference_no', 'billing_address', 'service_deadline', 'service_priority', 'document_delivery_method', 'document_name', 'affidavit_success_action', 'affidavit_unsuccessful_action'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            wp_send_json_error(['message' => 'Please fill in all required fields.']);
            wp_die();
        }
    }

    // Sanitize inputs
    $client_name = sanitize_text_field(wp_unslash($_POST['client_name']));
    $company_name = isset($_POST['company_name']) ? sanitize_text_field(wp_unslash($_POST['company_name'])) : '';
    $reference_no = sanitize_text_field(wp_unslash($_POST['reference_no']));
    $phone = sanitize_text_field(wp_unslash($_POST['phone']));
    $email = sanitize_email(wp_unslash($_POST['email']));
    $billing_address = sanitize_textarea_field(wp_unslash($_POST['billing_address']));
    $service_deadline = sanitize_text_field(wp_unslash($_POST['service_deadline']));
    $service_time = isset($_POST['service_time']) ? sanitize_text_field(wp_unslash($_POST['service_time'])) : '';
    $service_priority = sanitize_text_field(wp_unslash($_POST['service_priority']));
    $document_delivery_method = sanitize_text_field(wp_unslash($_POST['document_delivery_method']));
    $document_name = sanitize_text_field(wp_unslash($_POST['document_name']));
    $document_filed_date = isset($_POST['document_filed_date']) ? sanitize_text_field(wp_unslash($_POST['document_filed_date'])) : '';
    $affidavit_success_action = sanitize_text_field(wp_unslash($_POST['affidavit_success_action']));
    $affidavit_unsuccessful_action = sanitize_text_field(wp_unslash($_POST['affidavit_unsuccessful_action']));
    $mail_affidavit = isset($_POST['mail_affidavit']) ? 'Yes' : 'No';
    $terms_acknowledged = isset($_POST['terms_acknowledged']) ? 'Yes' : 'No';
    $final_terms = isset($_POST['final_terms']) ? 'Yes' : 'No';

    $recipient_name = isset($_POST['recipient_name']) ? sanitize_text_field(wp_unslash($_POST['recipient_name'])) : '';
    $recipient_phone = isset($_POST['recipient_phone']) ? sanitize_text_field(wp_unslash($_POST['recipient_phone'])) : '';
    $recipient_email = isset($_POST['recipient_email']) ? sanitize_email(wp_unslash($_POST['recipient_email'])) : '';
    $recipient_city = isset($_POST['recipient_city']) ? sanitize_text_field(wp_unslash($_POST['recipient_city'])) : '';
    $recipient_postal = isset($_POST['recipient_postal']) ? sanitize_text_field(wp_unslash($_POST['recipient_postal'])) : '';
    $recipient_address = isset($_POST['recipient_address']) ? sanitize_textarea_field(wp_unslash($_POST['recipient_address'])) : '';

    // Validate user's email
    if (! is_email($email)) {
        wp_send_json_error(['message' => 'Please enter a valid email address.']);
        wp_die();
    }

    // Upload config (matches UI: 6 files, 6MB each, 30MB total)
    $upload_limits = [
        'max_files'      => 6,
        'max_file_size'  => 6 * 1024 * 1024,
        'max_total_size' => 30 * 1024 * 1024,
        'allowed_types'  => [
            'pdf'  => 'application/pdf',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
        ],
    ];

    $uploaded_files = [];
    $total_size = 0;

    if (isset($_FILES['document_upload']) && is_array($_FILES['document_upload']['name'])) {
        $files = $_FILES['document_upload'];
        $count = count($files['name']);

        if ($count > $upload_limits['max_files']) {
            wp_send_json_error(['message' => 'Exceeded max number of files.']);
            wp_die();
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';

        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }

            $original_name = sanitize_file_name($files['name'][$i]);
            $size = intval($files['size'][$i]);
            $tmp_name = $files['tmp_name'][$i];

            $total_size += $size;
            if ($size > $upload_limits['max_file_size']) {
                wp_send_json_error(['message' => 'One of the files exceeds the maximum allowed file size.']);
                wp_die();
            }
            if ($total_size > $upload_limits['max_total_size']) {
                wp_send_json_error(['message' => 'Total upload size exceeds allowed limit.']);
                wp_die();
            }

            $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
            if (! array_key_exists($ext, $upload_limits['allowed_types'])) {
                wp_send_json_error(['message' => 'One of the files has an invalid file type.']);
                wp_die();
            }

            $tmp_file = [
                'name'     => $original_name,
                'type'     => $files['type'][$i],
                'tmp_name' => $tmp_name,
                'error'    => $files['error'][$i],
                'size'     => $size,
            ];

            $overrides = ['test_form' => false, 'mimes' => $upload_limits['allowed_types']];
            $result = wp_handle_upload($tmp_file, $overrides);

            if (isset($result['error'])) {
                wp_send_json_error(['message' => 'File upload error: ' . $result['error']]);
                wp_die();
            } else {
                $uploaded_files[] = [
                    'original_name' => $original_name,
                    'url'           => $result['url'],
                    'file'          => $result['file'],
                    'size'          => $size,
                    'type'          => isset($result['type']) ? $result['type'] : $files['type'][$i],
                ];
            }
        }
    }

    // Prepare email
    $to = 'info@calgarylegaldocs.ca'; // change if needed
    //$to = 'dharminder.developer@gmail.com'; // change if needed
    $subject = 'New Master Service Request - ' . $client_name;

    $message = '';
    $message .= '<h2>New Master Service Request</h2>';
    $message .= '<h3>Client Information</h3>';
    $message .= '<p><strong>Name:</strong> ' . esc_html($client_name) . '</p>';
    $message .= '<p><strong>Company Name:</strong> ' . esc_html($company_name) . '</p>';
    $message .= '<p><strong>Reference No:</strong> ' . esc_html($reference_no) . '</p>';
    $message .= '<p><strong>Phone:</strong> ' . esc_html($phone) . '</p>';
    $message .= '<p><strong>Email:</strong> ' . esc_html($email) . '</p>';
    $message .= '<p><strong>Billing Address:</strong><br>' . nl2br(esc_html($billing_address)) . '</p>';

    $message .= '<h3>Service Requirements</h3>';
    $message .= '<p><strong>Service Deadline:</strong> ' . esc_html($service_deadline) . '</p>';
    $message .= '<p><strong>Service Time:</strong> ' . esc_html($service_time) . '</p>';
    $message .= '<p><strong>Service Priority:</strong> ' . esc_html($service_priority) . '</p>';

    $message .= '<h3>Document Information</h3>';
    $message .= '<p><strong>Document Delivery Method:</strong> ' . esc_html($document_delivery_method) . '</p>';
    $message .= '<p><strong>Document Name:</strong> ' . esc_html($document_name) . '</p>';
    $message .= '<p><strong>Date Filed:</strong> ' . esc_html($document_filed_date) . '</p>';

    $message .= '<h3>Recipient Information</h3>';
    $message .= '<p><strong>Recipient Name:</strong> ' . esc_html($recipient_name) . '</p>';
    $message .= '<p><strong>Recipient Phone:</strong> ' . esc_html($recipient_phone) . '</p>';
    $message .= '<p><strong>Recipient Email:</strong> ' . esc_html($recipient_email) . '</p>';
    $message .= '<p><strong>Recipient City:</strong> ' . esc_html($recipient_city) . '</p>';
    $message .= '<p><strong>Recipient Postal:</strong> ' . esc_html($recipient_postal) . '</p>';
    $message .= '<p><strong>Recipient Address:</strong><br>' . nl2br(esc_html($recipient_address)) . '</p>';

    if (! empty($uploaded_files)) {
        $message .= '<h3>📎 Uploaded Documents</h3><ul>';
        foreach ($uploaded_files as $f) {
            $size_mb = round($f['size'] / 1024 / 1024, 2);
            $message .= '<li><strong>📄 ' . esc_html($f['original_name']) . '</strong><br>';
            $message .= '<small>Size: ' . esc_html($size_mb) . ' MB | Type: ' . esc_html($f['type']) . '</small><br>';
            $message .= '<a href="' . esc_url($f['url']) . '">🔗 Download Document</a></li>';
        }
        $message .= '</ul>';
    }

    $message .= '<h3>Affidavit Preferences</h3>';
    $message .= '<p><strong>Upon Success:</strong> ' . esc_html($affidavit_success_action) . '</p>';
    $message .= '<p><strong>If Unsuccessful:</strong> ' . esc_html($affidavit_unsuccessful_action) . '</p>';

    $message .= '<h3>Additional Options</h3>';
    $message .= '<p><strong>Mail Original Affidavit:</strong> ' . esc_html($mail_affidavit) . '</p>';
    $message .= '<p><strong>Terms Acknowledged:</strong> ' . esc_html($terms_acknowledged) . '</p>';
    $message .= '<p><strong>Final Terms Agreed:</strong> ' . esc_html($final_terms) . '</p>';

    $headers = [
        'From: Mastter Service <info@calgarylegaldocs.ca>',
        'Reply-To: ' . $email,
        'Content-Type: text/html; charset=UTF-8',
    ];

    $attachments = array_map(function ($f) {
        return $f['file'];
    }, $uploaded_files);

    $mail_result = wp_mail($to, $subject, $message, $headers, $attachments);

    // Build log entry
    $log_entry = [
        'timestamp'        => current_time('c'),
        'to'               => $to,
        'subject'          => $subject,
        'fields'           => [
            'client_name' => $client_name,
            'company_name' => $company_name,
            'reference_no' => $reference_no,
            'phone' => $phone,
            'email' => $email,
            'billing_address' => $billing_address,
            'service_deadline' => $service_deadline,
            'service_time' => $service_time,
            'service_priority' => $service_priority,
            'document_delivery_method' => $document_delivery_method,
            'document_name' => $document_name,
            'document_filed_date' => $document_filed_date,
            'recipient_name' => $recipient_name,
            'recipient_phone' => $recipient_phone,
            'recipient_email' => $recipient_email,
            'recipient_city' => $recipient_city,
            'recipient_postal' => $recipient_postal,
            'recipient_address' => $recipient_address,
            'mail_affidavit' => $mail_affidavit,
            'terms_acknowledged' => $terms_acknowledged,
            'final_terms' => $final_terms,
        ],
        'attachments'      => array_map(function ($f) {
            return $f['url'];
        }, $uploaded_files),
        'status'           => $mail_result ? 'sent' : 'failed',
        'wp_mail_result'   => $mail_result,
    ];

    // Append log (silently ignore if not writeable)
    if (function_exists('msttr_append_log')) {
        msttr_append_log($log_entry);
    }

    if (wp_doing_ajax()) {
        // shouldn't be via admin-ajax, but keep compatibility
        if ($mail_result) {
            wp_send_json_success(['message' => 'Form submitted successfully. We will contact you shortly.']);
        } else {
            wp_send_json_error(['message' => 'Failed to send request. Please try again later.']);
        }
    } else {
        // If request is from fetch() expecting JSON (we used fetch in JS), return JSON and exit.
        wp_send_json([
            'success' => $mail_result,
            'message' => $mail_result
                ? 'Form submitted successfully. We will contact you shortly.'
                : 'Failed to send request. Please try again later.'
        ]);
    }
}
