document.addEventListener('show.bs.modal', function (event) {
    if (event.target.id === 'category-modal') {
        const triggerButton = event.relatedTarget;
        const categoryId = triggerButton.getAttribute('data-id');
        let url = base_url + '/admin/categories/' + categoryId + '/edit';

        const form = document.querySelector('.form-submit');
        const imageUpload = document.querySelector('#image-upload');
        const bannerUpload = document.querySelector('#banner-upload');
        const iconUpload = document.querySelector('#icon-upload');
        const activeIconUpload = document.querySelector('#active-icon-upload');
        const backgroundImageUpload = document.querySelector('#background-image-upload');
        const backgroundTypeSelect = document.querySelector('#background-type-select');
        const modalTitle = document.querySelector('#category-modal .modal-title');
        const submitButton = document.querySelector('#category-modal button[type="submit"]');
        const parentSelect = document.getElementById("select-parent-category");
        const tomSelectInstance = parentSelect && parentSelect.tomselect; // TomSelect instance
// Remove files from FilePond if available
        if (typeof FilePond !== 'undefined') {
            const pond = FilePond.find(imageUpload);
            if (pond) pond.removeFiles();
            const bannerPond = FilePond.find(bannerUpload);
            if (bannerPond) bannerPond.removeFiles();
            const iconPond = FilePond.find(iconUpload);
            if (iconPond) iconPond.removeFiles();
            const activeIconPond = FilePond.find(activeIconUpload);
            if (activeIconPond) activeIconPond.removeFiles();
            const backgroundImagePond = FilePond.find(backgroundImageUpload);
            if (backgroundImagePond) backgroundImagePond.removeFiles();
        }
        if (categoryId) {
            // Fetch category data
            fetch(url, {method: 'GET'})
                .then(response => response.json())
                .then(async responseData => {
                    const data = responseData.data;
                    // Fill form fields
                    form.querySelector('input[name="title"]').value = data.title || '';
                    form.querySelector('input[id="category-id"]').value = categoryId;
                    form.querySelector('textarea[name="description"]').value = data.description || '';
                    form.querySelector('input[name="status"]').checked = data.status === 'active';
                    form.querySelector('input[name="requires_approval"]').checked = !!data.requires_approval;
                    form.querySelector('input[name="commission"]').value = data.commission || 0;

                    // Set background fields
                    if (backgroundTypeSelect) {
                        backgroundTypeSelect.value = data.background_type || '';
                        toggleBackgroundFields(data.background_type);
                    }
                    if (data.background_color) {
                        form.querySelector('input[name="background_color"]').value = data.background_color;
                    }
                    if (data.font_color) {
                        form.querySelector('input[name="font_color"]').value = data.font_color;
                    } else {

                        form.querySelector('input[name="font_color"]').value = '#00000';
                    }

                    // Set parent_id in TomSelect (auto-select)
                    if (tomSelectInstance) {
                        // If the parent is not in the options yet, load it
                        if (data.parent) {
                            let parentOption = tomSelectInstance.options[data.parent.id];
                            if (!parentOption) {
                                // Fetch the parent (if not already loaded)
                                await fetch(base_url + '/admin/categories/search' + `?q=${data.parent.title}`)
                                    .then(res => res.json())
                                    .then(json => {
                                        if (json && json.length) {
                                            tomSelectInstance.addOption(json[0]);
                                        }
                                    });
                            }
                            tomSelectInstance.setValue(data.parent_id);
                        } else {
                            tomSelectInstance.clear();
                        }
                    }

                    // Image upload via FilePond
                    if (typeof FilePond !== 'undefined') {
                        if (data.image !== null && data.image !== undefined && imageUpload && data.image !== '') {
                            const pond = FilePond.find(imageUpload);
                            if (pond) {
                                pond.addFile(data.image);
                            }
                        }
                        if (data.banner !== null && data.banner !== undefined && bannerUpload && data.banner !== '') {
                            const bannerPond = FilePond.find(bannerUpload);
                            if (bannerPond) {
                                bannerPond.addFile(data.banner);
                            }
                        }
                        if (data.icon !== null && data.icon !== undefined && iconUpload && data.icon !== '') {
                            const iconPond = FilePond.find(iconUpload);
                            if (iconPond) {
                                iconPond.addFile(data.icon);
                            }
                        }
                        if (data.active_icon !== null && data.active_icon !== undefined && activeIconUpload && data.active_icon !== '') {
                            const activeIconPond = FilePond.find(activeIconUpload);
                            if (activeIconPond) {
                                activeIconPond.addFile(data.active_icon);
                            }
                        }
                        if (data.background_image !== null && data.background_image !== undefined && backgroundImageUpload && data.background_image !== '') {
                            const backgroundImagePond = FilePond.find(backgroundImageUpload);
                            if (backgroundImagePond) {
                                backgroundImagePond.addFile(data.background_image);
                            }
                        }
                    }

                    // Change form action to update route
                    form.setAttribute('action', base_url + `/admin/categories/${categoryId}`);

                    // Update modal title and button
                    modalTitle.textContent = 'Edit Category';
                    submitButton.textContent = 'Update Category';
                })
                .catch(error => {
                    console.error('AJAX Error:', error);
                });
        } else {
            // New category mode
            if (form) form.reset();
            // Remove _method input if it exists
            const methodInput = form.querySelector('input[name="_method"]');
            if (methodInput) methodInput.parentNode.removeChild(methodInput);

            // Reset TomSelect
            if (tomSelectInstance) tomSelectInstance.clear();

            // Reset background fields
            if (backgroundTypeSelect) {
                backgroundTypeSelect.value = '';
                toggleBackgroundFields('');
            }
            form.querySelector('input[name="background_color"]').value = '';

            // Set action for create
            form.querySelector('input[id="category-id"]').value = "";
            form.setAttribute('action', base_url + '/admin/categories');
            modalTitle.textContent = 'Create Category';
            submitButton.textContent = 'Create new Category';
        }
    }
    if (event.target.id === 'faq-modal') {
        const triggerButton = event.relatedTarget;
        const conditionId = triggerButton ? triggerButton.getAttribute('data-id') : null;
        let url = `${base_url}/${panel}/faqs/${conditionId}/edit`;

        const form = document.querySelector('#faq-modal .form-submit');
        const modalTitle = document.querySelector('#faq-modal .modal-title');
        const submitButton = document.querySelector('#faq-modal button[type="submit"]');

        if (conditionId) {
            // Edit mode: Fetch and populate data
            fetch(url, {method: 'GET'})
                .then(response => response.json())
                .then(async responseData => {
                    const data = responseData.data;

                    // Fill form fields
                    form.querySelector('textarea[name="question"]').value = data.question || '';
                    form.querySelector('textarea[name="answer"]').value = data.answer || '';
                    form.querySelector('select[name="status"]').value = data.status || '';

                    // Change form action to update route
                    form.setAttribute('action', `${base_url}/${panel}/faqs/${conditionId}`);

                    // Update modal title and button
                    modalTitle.textContent = 'Edit Faq';
                    submitButton.innerHTML = '<i class="ti ti-edit me-1"></i> Update';
                })
                .catch(error => {
                    console.error('AJAX Error:', error);
                });
        } else {
            // New condition mode: Reset fields
            if (form) form.reset();
            form.querySelector('textarea[name="question"]').value = '';
            form.querySelector('textarea[name="answer"]').value = '';
            form.querySelector('select[name="status"]').value = 'active';
            // Set action for create
            form.setAttribute('action', `${base_url}/${panel}/faqs`);
            modalTitle.textContent = 'Add Faq';
            submitButton.innerHTML = '<i class="ti ti-plus me-1"></i> Add';
        }
    }
});

// Delete category
document.addEventListener('click', function (event) {
    handleDelete(event, '.delete-category', `/${panel}/categories/`, 'You are about to delete this Category.');
    handleDelete(event, '.delete-faq', `/${panel}/faqs/`, 'You are about to delete this Faq.');
});

// Background type toggle function
function toggleBackgroundFields(backgroundType) {
    const backgroundColorField = document.getElementById('background-color-field');
    const backgroundImageField = document.getElementById('background-image-field');

    if (backgroundType === 'color') {
        backgroundColorField.style.display = 'block';
        backgroundImageField.style.display = 'none';
    } else if (backgroundType === 'image') {
        backgroundColorField.style.display = 'none';
        backgroundImageField.style.display = 'block';
    } else {
        backgroundColorField.style.display = 'none';
        backgroundImageField.style.display = 'none';
    }
}

// Background type select event listener
document.addEventListener('change', function (event) {
    if (event.target.id === 'background-type-select') {
        toggleBackgroundFields(event.target.value);
    }
});

let tomSelectInstance;

try {
    tomSelectInstance = new TomSelect('.search-labels', {
        create: true,
        maxItems: 3
    });
} catch (e) {
}
document.querySelector('.generate-search-labels-button')?.addEventListener('click', function () {
    if (!tomSelectInstance) return;

    // Pool of random keywords
    const keywords = [
        'Grocery', 'Electronics', 'Daily Essentials', 'Fashion',
        'Beauty', 'Toys', 'Stationery', 'Books', 'Sports', 'Furniture'
    ];

    // Shuffle and pick 3 random keywords
    const randomKeywords = keywords.sort(() => 0.5 - Math.random()).slice(0, 3);

    // Clear old selections
    tomSelectInstance.clear();

    // Add "Search for ..." items
    randomKeywords.forEach(keyword => {
        const label = `Search for ${keyword}`;
        const value = label.toLowerCase().replace(/\s+/g, '_');
        tomSelectInstance.addOption({value, text: label});
        tomSelectInstance.addItem(value);
    });
});
