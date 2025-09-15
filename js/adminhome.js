document.addEventListener('DOMContentLoaded', function() {
    const burgerBtn = document.getElementById('burger-btn');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.querySelector('.main-content'); // Select the main-content

    if (burgerBtn && sidebar && mainContent) {
        burgerBtn.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            mainContent.classList.toggle('shifted'); // Toggle class for main content
        });

        // Close sidebar when clicking outside (on main content)
        mainContent.addEventListener('click', function(event) {
            if (sidebar.classList.contains('active') && !sidebar.contains(event.target) && !burgerBtn.contains(event.target)) {
                sidebar.classList.remove('active');
                mainContent.classList.remove('shifted');
            }
        });
    }

    // Handle image upload interactions for general sections
    document.querySelectorAll('.image-upload-container').forEach(container => {
        const fileInput = container.querySelector('input[type="file"]');
        const previewImg = container.querySelector('.image-preview');
        const uploadButton = container.closest('.form-group').querySelector('.btn-upload');
        const altInput = container.closest('.form-group').querySelector('input[type="text"]');
        const sectionName = container.dataset.section;
        const progressBar = container.querySelector('.progress-bar');
        const uploadProgress = container.querySelector('.upload-progress');

        if (!fileInput || !previewImg || !uploadButton || !altInput || !sectionName || !progressBar || !uploadProgress) {
            console.error('Missing elements in image upload container:', container);
            return;
        }

        // Click to open file dialog
        container.addEventListener('click', () => fileInput.click());

        // Prevent default drag behaviors
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            container.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        // Highlight drag area
        ['dragenter', 'dragover'].forEach(eventName => {
            container.addEventListener(eventName, () => container.classList.add('dragover'), false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            container.addEventListener(eventName, () => container.classList.remove('dragover'), false);
        });

        // Handle dropped files
        container.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            fileInput.files = files; // Assign dropped files to the file input
            displayImagePreview(files[0]);
        }

        // Handle file input change
        fileInput.addEventListener('change', (e) => {
            displayImagePreview(e.target.files[0]);
        });

        function displayImagePreview(file) {
            if (file) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    previewImg.src = e.target.result;
                    previewImg.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                previewImg.src = '#';
                previewImg.style.display = 'none';
            }
        }

        uploadButton.addEventListener('click', function(e) {
            e.stopPropagation(); // Prevent container click from re-triggering file input
            e.preventDefault(); // Prevent form submission if button is inside a form

            const file = fileInput.files[0];
            if (!file) {
                showModalMessage('Error', 'Please select an image to upload.', false);
                return;
            }

            const formData = new FormData();
            formData.append('image', file);
            formData.append('section', sectionName);
            formData.append('image_alt', altInput.value);
            formData.append('ajax_upload', '1'); // Indicate AJAX upload

            // Show progress bar
            uploadProgress.style.display = 'block';
            progressBar.style.width = '0%';
            progressBar.classList.remove('bg-success', 'bg-danger');
            progressBar.classList.add('bg-info');

            $.ajax({
                url: 'cms_homepage.php', // Your PHP script endpoint
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    const xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function(evt) {
                        if (evt.lengthComputable) {
                            const percentComplete = (evt.loaded / evt.total) * 100;
                            progressBar.style.width = percentComplete + '%';
                            progressBar.textContent = Math.round(percentComplete) + '%';
                        }
                    }, false);
                    return xhr;
                },
                success: function(response) {
                    if (response.success) {
                        showModalMessage('Success', response.message, true);
                        previewImg.src = response.path; // Update preview with the new path from server
                        previewImg.style.display = 'block';
                        altInput.value = altInput.value; // Keep the alt text
                        progressBar.classList.remove('bg-info');
                        progressBar.classList.add('bg-success');
                        setTimeout(() => uploadProgress.style.display = 'none', 1000); // Hide after a delay
                        
                        // Manually trigger input event on content fields to update preview
                        const relatedContentInput = document.getElementById(sectionName + '-preview');
                        if (relatedContentInput) {
                            const event = new Event('input', { bubbles: true });
                            relatedContentInput.dispatchEvent(event);
                        }

                    } else {
                        showModalMessage('Error', response.message, false);
                        progressBar.classList.remove('bg-info');
                        progressBar.classList.add('bg-danger');
                    }
                },
                error: function(xhr, status, error) {
                    showModalMessage('Error', 'Upload failed: ' + (xhr.responseJSON ? xhr.responseJSON.message : error), false);
                    progressBar.classList.remove('bg-info');
                    progressBar.classList.add('bg-danger');
                },
                complete: function() {
                    // Reset file input to allow re-uploading the same file if needed
                    fileInput.value = ''; 
                }
            });
        });
    });

});

// Global function to show modal messages (re-used from cms_homepage.php)
function showModalMessage(title, message, isSuccess) {
    const modalTitle = document.getElementById('messageModalLabel');
    const modalBody = document.getElementById('messageModalBody');
    const modal = new bootstrap.Modal(document.getElementById('messageModal'));

    modalTitle.textContent = title;
    modalBody.textContent = message;

    if (isSuccess) {
        modalTitle.classList.remove('text-danger');
        modalTitle.classList.add('text-success');
    } else {
        modalTitle.classList.remove('text-success');
        modalTitle.classList.add('text-danger');
    }
    modal.show();
}