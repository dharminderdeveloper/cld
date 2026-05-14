// Form submission handler and file manager (original logic, adapted to WP endpoint)
var fileManager;


// Modal functions
function showModal() {
    var el = document.getElementById('contactModal');
	console.log('modal');
    if (el) {
        el.style.display = 'block';
        document.body.style.overflow = 'hidden'; // Prevent scrolling when modal is open
    }
}

function closeModal() {
    var el = document.getElementById('contactModal');
    if (el) {
        el.style.display = 'none';
        document.body.style.overflow = 'auto'; // Re-enable scrolling
    }
}

// File upload management
class FileUploadManager {
    constructor() {
        this.selectedFiles = [];
        this.maxFiles = 6;
        this.maxFileSize = 6 * 1024 * 1024; // 6MB
        this.maxTotalSize = 30 * 1024 * 1024; // 30MB

        this.hiddenInput = document.getElementById('hiddenFileInput');
        this.addBtn = document.getElementById('addFileBtn');
        this.fileListContainer = document.getElementById('fileListContainer');
        this.errorMsg = document.querySelector('.file-upload-error-msg');
    }

    init() {
        if (this.addBtn) {
            this.addBtn.addEventListener('click', () => this.triggerFileSelect());
        }
        if (this.hiddenInput) {
            this.hiddenInput.addEventListener('change', (e) => this.handleFileSelect(e));
        }
    }

    triggerFileSelect() {
        this.clearError();
        if (this.hiddenInput) {
            this.hiddenInput.value = ''; // Reset input
            this.hiddenInput.click();
        }
    }

    handleFileSelect(event) {
        const file = event.target.files[0];
        if (!file) return;

        // Validate file
        if (!this.validateFile(file)) {
            return;
        }

        // Add file to collection
        this.selectedFiles.push(file);
        this.updateDisplay();
        this.updateButtonState();
    }

    validateFile(file) {
        // Check file count
        if (this.selectedFiles.length >= this.maxFiles) {
            this.showError(`Maximum ${this.maxFiles} files allowed.`);
            return false;
        }

        // Check file size
        if (file.size > this.maxFileSize) {
            this.showError(`File "${file.name}" is ${this.formatFileSize(file.size)}. Maximum 6MB per file.`);
            return false;
        }

        // Check total size
        const currentTotalSize = this.getTotalSize();
        if (currentTotalSize + file.size > this.maxTotalSize) {
            this.showError(`Adding "${file.name}" would exceed 30MB total limit.`);
            return false;
        }

        // Check file type
        const allowedTypes = ['.pdf', '.doc', '.docx', '.jpg', '.jpeg', '.png'];
        const fileExtension = '.' + file.name.split('.').pop().toLowerCase();
        if (!allowedTypes.includes(fileExtension)) {
            this.showError(`File type not allowed. Allowed types: ${allowedTypes.join(', ')}`);
            return false;
        }

        return true;
    }

    removeFile(index) {
        this.selectedFiles.splice(index, 1);
        this.updateDisplay();
        this.updateButtonState();
        this.clearError();
    }

    updateDisplay() {
        if (!this.fileListContainer) return;
        this.fileListContainer.innerHTML = '';

        this.selectedFiles.forEach((file, index) => {
            const fileItem = document.createElement('div');
            fileItem.style.cssText = `padding: 6px; margin-bottom: 5px; background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; font-size:12px`;

            fileItem.innerHTML = `               
                ${file.name} (${this.formatFileSize(file.size)})               
                <button type="button" class="ms-btn ms-btn-sm ms-btn-danger" onclick="fileManager.removeFile(${index})">
                    Remove
                </button>
            `;

            this.fileListContainer.appendChild(fileItem);
        });

        // Show total size info
        if (this.selectedFiles.length > 0) {
            const totalInfo = document.createElement('div');
            totalInfo.style.cssText = `margin-top: 5px; padding: 5px; background-color: #e9ecef; border-radius: 3px; font-size: 0.9em;`;
            totalInfo.textContent = `Total: ${this.selectedFiles.length}/${this.maxFiles} files, ${this.formatFileSize(this.getTotalSize())}/30MB`;
            this.fileListContainer.appendChild(totalInfo);
        }
    }

    updateButtonState() {
        const isAtLimit = this.selectedFiles.length >= this.maxFiles ||
            this.getTotalSize() >= this.maxTotalSize;

        if (this.addBtn) {
            this.addBtn.disabled = isAtLimit;
            this.addBtn.textContent = isAtLimit ?
                (this.selectedFiles.length >= this.maxFiles ? 'Maximum Files Reached' : 'Size Limit Reached') :
                '+ Add File';
        }
    }

    getTotalSize() {
        return this.selectedFiles.reduce((total, file) => total + file.size, 0);
    }

    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    showError(message) {
        if (this.errorMsg) {
            this.errorMsg.textContent = message;
            this.errorMsg.style.display = 'block';
        }
    }

    clearError() {
        if (this.errorMsg) {
            this.errorMsg.textContent = '';
            this.errorMsg.style.display = 'none';
        }
    }

    reset() {
        this.selectedFiles = [];
        this.updateDisplay();
        this.updateButtonState();
        this.clearError();
    }

    // Method to get files for form submission
    getFiles() {
        return this.selectedFiles;
    }
}

// Show loader function
function showLoader(loaderId = 'ajaxLoader') {
    const loader = document.getElementById(loaderId);
    if (loader) {
        loader.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

// Hide loader function
function hideLoader(loaderId = 'ajaxLoader') {
    const loader = document.getElementById(loaderId);
    if (loader) {
        loader.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

document.addEventListener("DOMContentLoaded", function () {
	 
	
	var ctaButtons = document.querySelectorAll(".home-cta-1 a, .btn-yellow");

ctaButtons.forEach(function(btn) {
    btn.addEventListener("click", function(e) {
        console.log("CTA clicked");
		e.preventDefault(); // prevent default if you want custom behavior
		console.log("Start Your Service Now button clicked!");
		 showModal(); // or any function you want to trigger
    });
});


	// Add click listener (example)
// 	if (ctaButton) {
// 	  ctaButton.addEventListener("click", function (e) {
// 		e.preventDefault(); // prevent default if you want custom behavior
// 		console.log("Start Your Service Now button clicked!");
// 		 showModal(); // or any function you want to trigger
		
// 	  });
// 	}	
	
    // Initialize file manager here when DOM is ready
    fileManager = new FileUploadManager();
    fileManager.init();

    // Form submission handler
    var theForm = document.getElementById("masterServiceForm");
    if (!theForm) return;

    theForm.addEventListener("submit", function (e) {
        e.preventDefault();

        const form = e.target;
        const submitButton = form.querySelector('button[type="submit"]');
        const successMsg = document.querySelector(".success-msg");
        const errorMsg = document.querySelector(".error-msg");

        // Create FormData and add all form fields
        const formData = new FormData(form);

        // Ensure action and nonce are present
        if (typeof mastterServiceConf !== 'undefined') {
            formData.append('action', 'mastter_service_submit');
            formData.append('_mastter_service_nonce', mastterServiceConf.nonce);
        } else {
            // fallback
            formData.append('action', 'mastter_service_submit');
        }

        // Remove any existing file inputs from FormData and add our managed files
        formData.delete("document_upload[]");

        // Add selected files to FormData
        const selectedFiles = fileManager.getFiles();
        selectedFiles.forEach((file, index) => {
            formData.append("document_upload[]", file);
        });

        // Hide both messages initially
        if (successMsg) successMsg.style.display = "none";
        if (errorMsg) errorMsg.style.display = "none";

        // Show loading state
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.innerHTML =
                '<span style="display: inline-block; width: 20px; height: 20px; border: 2px solid #ffffff; border-radius: 50%; border-top-color: transparent; animation: spin 1s linear infinite; margin-right: 10px;"></span>Processing your request…';
        }

        showLoader();

        // Use post_url passed from PHP
        var postUrl = (typeof mastterServiceConf !== 'undefined' && mastterServiceConf.post_url) ? mastterServiceConf.post_url : '/wp-admin/admin-post.php';

        fetch(postUrl, {
            method: "POST",
            body: formData,
        }).then((response) => response.json())
            .then((data) => {
                hideLoader();
                if (data.success) {
                    // Reset form and file manager
                    form.reset();
                    fileManager.reset();

                    if (successMsg) {
                        successMsg.style.display = "block";
                        successMsg.textContent = data.message;
                    }

                    // Close modal after 3 seconds
                    setTimeout(() => {
                        closeModal();
                        if (successMsg) successMsg.style.display = "none";
                    }, 3000);
                } else {
                    // Show error message
                    if (errorMsg) {
                        errorMsg.textContent = data.message || 'An error occurred.';
                        errorMsg.style.display = "block";
                    }
                }
            })
            .catch((error) => {
                console.error("Error:", error);
                hideLoader();
                if (errorMsg) {
                    errorMsg.textContent =
                        "There was a network error. Please check your connection and try again.";
                    errorMsg.style.display = "block";
                }
            })
            .finally(() => {
                // Reset button state
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.innerHTML = "Submit Service Request";
                }
            });
    });
});
