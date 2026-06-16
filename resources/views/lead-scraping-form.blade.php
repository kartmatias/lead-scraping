<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lead Scraping Form</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#FDFDFC] dark:bg-[#0a0a0a] text-[#1b1b18] flex p-6 lg:p-8 items-center lg:justify-center min-h-screen flex-col">
    <div class="max-w-md w-full space-y-6">
        <form id="leadScrapeForm" class="space-y-4">
            <div>
                <label for="actorSelect" class="block text-sm font-medium text-gray-700 mb-2">Select Data Source</label>
                <select id="actorSelect" name="source" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    <option value="">-- Select Source --</option>
                    <option value="google_maps">Google Maps</option>
                    <option value="instagram">Instagram</option>
                    <option value="linkedin">LinkedIn</option>
                    <option value="cnpj">CNPJ (Brazil)</option>
                </select>
            </div>

            <div id="actorFields">
                <!-- Fields will be dynamically inserted here based on selected actor -->
            </div>

            <div class="flex items-center justify-between">
                <button type="submit" id="submitButton" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed">
                    Start Scraping
                </button>
                <span id="formStatus" class="ml-3 text-sm hidden"></span>
            </div>
        </form>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const actorSelect = document.getElementById('actorSelect');
        const actorFields = document.getElementById('actorFields');
        const form = document.getElementById('leadScrapeForm');
        const submitButton = document.getElementById('submitButton');
        const formStatus = document.getElementById('formStatus');

        // Define fields for each actor type
        const actorFieldsConfig = {
            google_maps: [
                {
                    label: 'Search Strings',
                    name: 'searchStringsArray',
                    type: 'text',
                    placeholder: 'e.g., restaurants in São Paulo',
                    help: 'Enter one or more search strings, separated by commas'
                },
                {
                    label: 'Maximum Items',
                    name: 'maxItems',
                    type: 'number',
                    placeholder: '10',
                    min: '1',
                    max: '100'
                }
            ],
            instagram: [
                {
                    label: 'Instagram URLs',
                    name: 'directUrls',
                    type: 'text',
                    placeholder: 'e.g., https://www.instagram.com/username/',
                    help: 'Enter one or more Instagram profile URLs, separated by commas'
                },
                {
                    label: 'Results Limit',
                    name: 'resultsLimit',
                    type: 'number',
                    placeholder: '10',
                    min: '1',
                    max: '50'
                }
            ],
            linkedin: [
                {
                    label: 'LinkedIn Profile URLs',
                    name: 'profileUrls',
                    type: 'text',
                    placeholder: 'e.g., https://www.linkedin.com/in/username/',
                    help: 'Enter one or more LinkedIn profile URLs, separated by commas<br><small>Note: Requires Apify account with full-permission approval for LinkedIn scraper</small>'
                },
                {
                    label: 'Results Limit',
                    name: 'resultsLimit',
                    type: 'number',
                    placeholder: '10',
                    min: '1',
                    max: '50'
                }
            ],
            cnpj: [
                {
                    label: 'CNPJ Numbers',
                    name: 'cnpjs',
                    type: 'text',
                    placeholder: 'e.g., 00000000000191',
                    help: 'Enter one or more CNPJ numbers, separated by commas'
                }
            ]
        };

        // Update fields when actor selection changes
        actorSelect.addEventListener('change', function() {
            const selectedActor = this.value;
            clearFields();
            clearStatus();

            if (selectedActor && actorFieldsConfig[selectedActor]) {
                const fields = actorFieldsConfig[selectedActor];
                fields.forEach(fieldConfig => {
                    const fieldGroup = document.createElement('div');
                    fieldGroup.className = 'space-y-2';

                    const label = document.createElement('label');
                    label.htmlFor = fieldConfig.name;
                    label.className = 'block text-sm font-medium text-gray-700';
                    label.innerHTML = fieldConfig.label;

                    const input = document.createElement('input');
                    input.type = fieldConfig.type;
                    input.id = fieldConfig.name;
                    input.name = fieldConfig.name;
                    input.className = 'block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm';
                    input.placeholder = fieldConfig.placeholder;

                    // Add attributes for validation
                    if (fieldConfig.min) input.min = fieldConfig.min;
                    if (fieldConfig.max) input.max = fieldConfig.max;
                    if (fieldConfig.required) input.required = true;

                    fieldGroup.appendChild(label);
                    fieldGroup.appendChild(input);

                    // Add help text if provided
                    if (fieldConfig.help) {
                        const helpText = document.createElement('p');
                        helpText.className = 'text-xs text-gray-500';
                        helpText.innerHTML = fieldConfig.help;
                        fieldGroup.appendChild(helpText);
                    }

                    actorFields.appendChild(fieldGroup);
                });
            }
        });

        // Form submission handling
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            // Clear previous status
            clearStatus();

            // Validate form
            if (!validateForm()) {
                return;
            }

            // Show loading state
            submitButton.disabled = true;
            submitButton.innerHTML = 'Scraping...';
            formStatus.className = 'ml-3 text-sm text-blue-600';
            formStatus.textContent = 'Submitting request...';
            formStatus.classList.remove('hidden');

            // Collect form data
            const formData = new FormData(form);
            const data = {};

            // Process form data according to actor type
            const source = actorSelect.value;
            data.source = source;

            // Process fields based on actor type
            switch(source) {
                case 'google_maps':
                    const searchStrings = formData.get('searchStringsArray');
                    data.filters = {
                        searchStringsArray: searchStrings.split(',').map(s => s.trim()),
                        maxItems: parseInt(formData.get('maxItems')) || 10
                    };
                    break;

                case 'instagram':
                    const directUrls = formData.get('directUrls');
                    data.filters = {
                        directUrls: directUrls.split(',').map(u => u.trim()),
                        resultsLimit: parseInt(formData.get('resultsLimit')) || 10
                    };
                    break;

                case 'linkedin':
                    const profileUrls = formData.get('profileUrls');
                    data.filters = {
                        profileUrls: profileUrls.split(',').map(u => u.trim()),
                        resultsLimit: parseInt(formData.get('resultsLimit')) || 10
                    };
                    break;

                case 'cnpj':
                    const cnpjs = formData.get('cnpjs');
                    data.filters = {
                        cnpjs: cnpjs.split(',').map(c => c.trim()).filter(c => c.length > 0)
                    };
                    break;
            }

            // Submit to API
            fetch('/api/scrape-requests', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(data)
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(err => { throw new Error(err.message || 'Failed to submit request'); });
                }
                return response.json();
            })
            .then(data => {
                showSuccess(`Scrape request submitted successfully! Request ID: ${data.id}`);

                // Start polling for status
                pollRequestStatus(data.id);

                form.reset();
                clearFields();
            })
            .catch(error => {
                showError(error.message || 'An error occurred while submitting the request');
            })
            .finally(() => {
                submitButton.disabled = false;
                submitButton.innerHTML = 'Start Scraping';
            });
        });

        // Helper functions
        function clearFields() {
            actorFields.innerHTML = '';
        }

        function clearStatus() {
            formStatus.className = 'ml-3 text-sm hidden';
            formStatus.textContent = '';
        }

        function showSuccess(message) {
            formStatus.className = 'ml-3 text-sm text-green-600';
            formStatus.textContent = message;
            formStatus.classList.remove('hidden');
        }

        function showError(message) {
            formStatus.className = 'ml-3 text-sm text-red-600';
            formStatus.textContent = message;
            formStatus.classList.remove('hidden');
        }

        function showInfo(message) {
            formStatus.className = 'ml-3 text-sm text-blue-600';
            formStatus.textContent = message;
            formStatus.classList.remove('hidden');
        }

        function validateForm() {
            // Clear previous error styles
            const inputs = actorFields.querySelectorAll('input');
            inputs.forEach(input => {
                input.classList.remove('border-red-500');
            });

            let isValid = true;

            // Check if actor is selected
            if (!actorSelect.value) {
                actorSelect.classList.add('border-red-500');
                showError('Please select a data source');
                isValid = false;
            } else {
                actorSelect.classList.remove('border-red-500');
            }

            // Validate required fields based on actor type
            const selectedActor = actorSelect.value;
            if (selectedActor && actorFieldsConfig[selectedActor]) {
                actorFieldsConfig[selectedActor].forEach(fieldConfig => {
                    const input = document.getElementById(fieldConfig.name);
                    if (input) {
                        // Check if required
                        if ((fieldConfig.type !== 'checkbox' && !input.value) ||
                            (fieldConfig.type === 'number' && (!input.value || parseInt(input.value) < parseInt(fieldConfig.min || 0)))) {
                            input.classList.add('border-red-500');
                            isValid = false;
                        }
                    }
                });
            }

            return isValid;
        }

        // Status polling functions
        let statusPollInterval = null;

        function pollRequestStatus(requestId) {
            showInfo(`Checking status of request #${requestId}...`);

            // Clear any existing interval
            if (statusPollInterval) {
                clearInterval(statusPollInterval);
            }

            // Poll every 5 seconds
            statusPollInterval = setInterval(async () => {
                try {
                    const response = await fetch(`/api/scrape-requests/${requestId}/status`);
                    if (!response.ok) {
                        throw new Error('Failed to fetch status');
                    }
                    const data = await response.json();

                    // Update status display
                    const statusMessages = {
                        pending: 'Waiting to start...',
                        running: 'Scraping in progress...',
                        completed: 'Scraping completed!',
                        failed: 'Scraping failed',
                        cancelled: 'Scraping cancelled'
                    };

                    const statusClassMap = {
                        pending: 'text-blue-600',
                        running: 'text-blue-600',
                        completed: 'text-green-600',
                        failed: 'text-red-600',
                        cancelled: 'text-yellow-600'
                    };

                    showInfo(`${statusMessages[data.status] || data.status}`);
                    formStatus.className = `ml-3 text-sm ${statusClassMap[data.status] || 'text-gray-600'}`;

                    // Stop polling if completed, failed, or cancelled
                    if (['completed', 'failed', 'cancelled'].includes(data.status)) {
                        clearInterval(statusPollInterval);
                        statusPollInterval = null;

                        // Show final result
                        if (data.status === 'completed') {
                            showSuccess(`Scraping completed! Found ${data.total_leads || 0} leads.`);
                        } else if (data.status === 'failed') {
                            showError(`Scraping failed: ${data.error_message || 'Unknown error'}`);
                        } else if (data.status === 'cancelled') {
                            showInfo('Scraping was cancelled.');
                        }
                    }
                } catch (error) {
                    console.error('Error polling status:', error);
                    showError('Error checking status');
                    clearInterval(statusPollInterval);
                    statusPollInterval = null;
                }
            }, 5000); // Poll every 5 seconds
        }

        // Clean up on page unload
        window.addEventListener('beforeunload', function() {
            if (statusPollInterval) {
                clearInterval(statusPollInterval);
            }
        });
    });
    </script>
</body>
</html>