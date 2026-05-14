<?php

/**
 * Plugin Name: Master Service
 * Plugin URI:  https://github.com/dharminderdeveloper
 * Description: Master Service Log form handling — file uploads, email via wp_mail, and daily JSON logging.
 * Version:     1.1
 * Author:      Dharminder Singh
 * Author URI:  https://github.com/dharminderdeveloper
 * Text Domain: master-service
 */

if (! defined('ABSPATH')) {
    exit;
}

define('MSTTR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MSTTR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MSTTR_LOGS_DIR', MSTTR_PLUGIN_DIR . 'logs/');

require_once MSTTR_PLUGIN_DIR . 'includes/logger.php';
require_once MSTTR_PLUGIN_DIR . 'includes/form-handler.php';

class Master_Service_Plugin_Main
{

    public function __construct()
    {
        register_activation_hook(__FILE__, [$this, 'on_activation']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_shortcode('mastter_service_form', [$this, 'render_form_shortcode']);

        // hook form handler defined in includes/form-handler.php
        add_action('admin_post_nopriv_master_service_submit', 'msttr_handle_form_submission');
        add_action('admin_post_master_service_submit', 'msttr_handle_form_submission');
    }

    public function on_activation()
    {
        if (! file_exists(MSTTR_LOGS_DIR)) {
            wp_mkdir_p(MSTTR_LOGS_DIR);
        }
        // deny direct access to logs via .htaccess (Apache). Silently ignore if cannot write.
        $htaccess = MSTTR_LOGS_DIR . '.htaccess';
        if (! file_exists($htaccess)) {
            @file_put_contents($htaccess, "Order allow,deny\nDeny from all\n");
        }
    }

    public function enqueue_assets()
    {
        // CSS
        wp_enqueue_style(
            'master-frontend-css',
            MSTTR_PLUGIN_URL . 'assets/css/master-service.css',
            [],
            '1.0.0'
        );

        // JS
        wp_enqueue_script(
            'master-frontend-js',
            MSTTR_PLUGIN_URL . 'assets/js/master-service.js',
            ['jquery'],
            '1.0.0',
            true
        );

        // Provide post_url and nonce to JS
        wp_localize_script(
            'master-frontend-js',
            'masterServiceConf',
            [
                'post_url' => admin_url('admin-post.php'),
                'nonce'    => wp_create_nonce('master_service_nonce'),
            ]
        );
    }

    public function render_form_shortcode($atts = [])
    {
        // Return your HTML exactly (kept unchanged). This is the same markup you provided.
        ob_start();
        ?>
            <div id="contactModal" class="modal">
                <div class="modal-content" style="max-width: 900px;">
                    <span class="close-ms-btn" onclick="closeModal()">&times;</span>
                    <h2 style="color: var(--navy); margin-bottom: 1.5rem; text-align: center;">MASTER SERVICE LOG</h2>

                    <form id="masterServiceForm" enctype="multipart/form-data">
                        <div class="d-grid grid-col-2">
                            <!-- Left Column -->
                            <div class="form-column">
                                <!-- YOUR INFORMATION Section -->
                                <div class="form-section">
                                    <h3>YOUR INFORMATION</h3>
                                    <div class="form-group">
                                        <label>Name <span class="text-error">*</span></label>
                                        <input type="text" name="client_name" placeholder="Enter your full name" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Company Name</label>
                                        <input type="text" name="company_name" placeholder="Enter your company name">
                                    </div>
                                    <div class="form-group">
                                        <label>Your File / Reference No <span class="text-error">*</span></label>
                                        <input type="text" name="reference_no"
                                            placeholder="Enter your file/reference number" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Phone Number <span class="text-error">*</span></label>
                                        <input type="tel" name="phone" placeholder="Enter your phone number" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Email Address <span class="text-error">*</span></label>
                                        <input type="email" name="email" placeholder="Enter your email address" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Billing Address <span class="text-error">*</span></label>
                                        <textarea name="billing_address" rows="3" placeholder="Enter your billing address"
                                            required></textarea>
                                    </div>
                                </div>

                                <!-- ABOUT YOUR SERVICE REQUIREMENTS Section -->
                                <div class="form-section">
                                    <h3>ABOUT YOUR SERVICE REQUIREMENTS</h3>
                                    <div class="form-group">
                                        <label>Service Deadline <span class="text-error">*</span></label>
                                        <input type="date" name="service_deadline" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Time</label>
                                        <input type="time" name="service_time">
                                    </div>
                                    <div class="form-group">
                                        <label>Service Priority <span class="text-error">*</span></label>
                                        <select name="service_priority" required>
                                            <option value="">Select service priority</option>
                                            <option value="Regular Service - $80.00">Regular Service - $80.00
                                            </option>
                                            <option value="Rush Service - $100.00">Rush Service - $100.00</option>
                                            <option value="Same Day Service - $120.00">Same Day Service - $120.00</option>
                                        </select>

                                    </div>

                                </div>
                            </div>

                            <!-- Right Column -->
                            <div class="form-column">
                                <!-- ABOUT YOUR SERVICE DOCUMENTS Section -->
                                <div class="form-section">
                                    <h3>ABOUT YOUR SERVICE DOCUMENTS</h3>
                                    <div class="form-group">
                                        <label>How do you wish Process Serving to Receive your Documents? <span
                                                class="text-error">*</span></label>
                                        <select name="document_delivery_method" required>
                                            <option value="">Select delivery method</option>
                                            <option value="Upload Documents Now">Upload Documents Now</option>
                                            <option value="Email Documents">Email Documents</option>
                                            <option value="Physical Drop-off">Physical Drop-off</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Upload your Documents Here <span class="text-error">*</span></label>
                                        <!-- Hidden file input -->
                                        <input type="file" id="hiddenFileInput" accept=".pdf,.doc,.docx,.jpg,.png,.jpeg"
                                            style="display: none;">
                                        <!-- Add file button -->
                                        <button type="button" id="addFileBtn" class="ms-btn ms-btn-primary mb-1">
                                            + Add File
                                        </button>
                                        <!-- File list display -->
                                        <div id="fileListContainer"></div>
                                        <!-- File constraints info -->
                                        <small style="color: #666;">
                                            Maximum 6 files, 6MB per file, 30MB total
                                        </small>
                                        <div class="msg file-upload-error-msg" style="display: none;"></div>
                                    </div>

                                    <div class="form-group">
                                        <label>Name of Documents</label>
                                        <input type="text" name="document_name" placeholder="Enter document name" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Date Filed</label>
                                        <input type="date" name="document_filed_date">
                                    </div>
                                </div>

                                <!-- ABOUT YOUR AFFIDAVIT Section -->
                                <div class="form-section">
                                    <h3>ABOUT YOUR AFFIDAVIT</h3>
                                    <div class="form-group">
                                        <label>Upon Success, do you wish to have Process Serving File the completed
                                            Affidavit of Service with the Court on your behalf ? <span
                                                class="text-error">*</span></label>
                                        <select name="affidavit_success_action" required>
                                            <option value="">Select an option</option>
                                            <option value="file_and_send">Yes, please File the Completed Affidavit
                                                of Service with the Court on my behalf and send me a Filed Copy</option>
                                            <option value="send_only">No, please send me the Completed Affidavit only
                                            </option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>If Unsuccessful, do you wish to have Process Serving File the completed
                                            Affidavit of Attempted Service with the Court on your behalf ? <span
                                                class="text-error">*</span></label>
                                        <select name="affidavit_unsuccessful_action" required>
                                            <option value="">Select an option</option>
                                            <option value="send_only">No, please send me the Completed Affidavit of
                                                Attempted Service and I will file them with the Court</option>
                                            <option value="file_and_send">Yes, please file the Affidavit of Attempted
                                                Service</option>
                                        </select>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <!-- WHO IS BEING SERVED? -->
                        <div class="form-row">
                            <h3 class="heading-hr">WHO IS BEING SERVED?</h3>
                            <div class="d-grid grid-col-2">
                                <div class="form-column">
                                    <div class="form-section">
                                        <div class="form-group">
                                            <label>Recipient Full Name <span class="text-error">*</span></label>
                                            <input type="text" name="recipient_name" placeholder="Enter recipient full name"
                                                required>
                                        </div>
                                        <div class="form-group">
                                            <label>Recipient Phone</label>
                                            <input type="phone" name="recipient_phone" placeholder="Phone number">
                                        </div>
                                        <div class="form-group">
                                            <label>Recipient Postal Code</label>
                                            <input type="text" name="recipient_postal" placeholder="Postal code">
                                        </div>
                                    </div>
                                </div>
                                <div class="form-column">
                                    <div class="form-section">
                                        <div class="form-group">
                                            <label>Recipient Email <span class="text-error">*</span></label>
                                            <input type="email" name="recipient_email" placeholder="Email address" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Recipient City <span class="text-error">*</span></label>
                                            <input type="text" name="recipient_city" placeholder="City name" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Address <span class="text-error">*</span></label>
                                            <textarea name="recipient_address" rows="3" placeholder="Enter address"
                                                required></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- FINAL ACKNOWLEDGEMENT Section -->
                        <div class="form-row">
                            <div class="form-group">
                                <input type="checkbox" name="mail_affidavit" style="width:auto;margin-right: 0.5rem;">
                                <span>Please mail the Original Affidavit(s) to the Billing Address Previously
                                    Indicated.</span>
                                </label>
                            </div>
                            <div class="form-group">
                                <input type="checkbox" name="terms_acknowledged" required
                                    style="width:auto;margin-right: 0.5rem;">
                                <span>Yes I have Read the above and Understand the Provisions of the Regular Service
                                    Priority I have Selected in this Submission. <span class="text-error">*</span></span>
                            </div>

                            <div class="form-group">
                                <input type="checkbox" name="final_terms" required style="width:auto;margin-right: 0.5rem;">
                                <span>I AGREE WITH THE TERMS AND CONDITIONS <span class="text-error">*</span></span>
                            </div>
                        </div>

                        <!-- Full width submit button -->
                        <div style="grid-column: 1 / -1; text-align: center; margin-top: 1rem;">
                            <button type="submit" class="ms-btn ms-btn-primary" style="padding: 12px 40px;">Submit Service
                                Request</button>
                        </div>
                        <div class="msg error-msg" style="display: none;"></div>
                        <div class="msg success-msg" style="display: none;"></div>
                    </form>
                </div>
                <div id="ajaxLoader" class="ajax-loader-overlay">
                    <div class="ajax-loader"></div>
                </div>
            </div>
        <?php
        return ob_get_clean();
    }
}

new Master_Service_Plugin_Main();
