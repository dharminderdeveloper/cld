Master Service - WordPress Plugin (v1.0)
Author: Dharminder Singh

Installation:
1. Upload the 'master-service' folder to wp-content/plugins/
2. Activate the plugin via WordPress admin Plugins screen.
3. Place the shortcode [master_service_form] in any page/post where you want the modal/form markup to be available.
   (The modal is initially display:none — you should provide a trigger button on the page that calls showModal())

Files:
- master-service.php             (main plugin bootstrap)
- assets/css/frontend.css         (form styling)
- assets/js/frontend.js           (form logic, uses masterServiceConf.post_url & masterServiceConf.nonce)
- includes/form-handler.php       (handles submission, uploads, and sending email via wp_mail)
- includes/logger.php             (writes daily JSON logs to logs/YYYY-MM-DD.json)
- logs/                           (auto-created folder for JSON logs)

Important notes:
- The plugin sends email to info@calgarylegaldocs.ca by default. Change inside includes/form-handler.php if necessary.
- Uploaded files are stored in the WordPress uploads folder (wp-content/uploads/...) using wp_handle_upload.
- Daily logs are stored in wp-content/plugins/master-service/logs/YYYY-MM-DD.json.
- Ensure PHP process can write to plugin folder for logs (or change logs path).
- The JS expects masterServiceConf.post_url and masterServiceConf.nonce (localized from PHP).
- The hidden file input (id="hiddenFileInput") will be used by the JS manager. It does not have a name attribute — JS appends files to FormData as "document_upload[]".

Security:
- Nonce verification and input sanitization are implemented.
- File type and size validation are enforced server-side and client-side.
