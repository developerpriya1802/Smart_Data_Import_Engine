<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Data Import Engine</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .drop-zone {
            border: 2px dashed #ccc;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        .drop-zone:hover, .drop-zone.dragover {
            border-color: #0d6efd;
            background-color: #f8f9fa;
        }
        .mapping-row {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        .progress-card {
            position: sticky;
            top: 20px;
        }
        .status-badge {
            font-size: 0.9rem;
            padding: 5px 15px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="/">Smart Data Import Engine</a>
        </div>
    </nav>

    <div class="container mt-5">
        <h1 class="mb-4">📥 Data Import</h1>
        
        <!-- Upload Section -->
        <div class="card mb-4" id="uploadSection">
            <div class="card-header">
                <h5>Step 1: Upload File</h5>
            </div>
            <div class="card-body">
                <div class="drop-zone" id="dropZone">
                    <p class="mb-2">Drag & drop your CSV or Excel file here</p>
                    <p class="text-muted">or</p>
                    <input type="file" id="fileInput" accept=".csv,.xlsx,.xls" style="display: none;">
                    <button class="btn btn-primary" onclick="document.getElementById('fileInput').click()">
                        Browse Files
                    </button>
                    <p class="mt-3 text-muted small">Supported: CSV, XLSX, XLS (Max 10MB)</p>
                </div>
                <div id="uploadStatus" class="mt-3"></div>
            </div>
        </div>

        <!-- Mapping Section -->
        <div class="card mb-4" id="mappingSection" style="display: none;">
            <div class="card-header">
                <h5>Step 2: Column Mapping</h5>
            </div>
            <div class="card-body">
                <div id="mappingContainer"></div>
                <div class="mt-3">
                    <button class="btn btn-success" id="saveMappingBtn">Save Mapping</button>
                </div>
            </div>
        </div>

        <!-- Import Section -->
        <div class="card mb-4" id="importSection" style="display: none;">
            <div class="card-header">
                <h5>Step 3: Start Import</h5>
            </div>
            <div class="card-body">
                <label class="form-label" for="validationRulesInput">Validation Rules</label>
                <textarea class="form-control mb-3" id="validationRulesInput" rows="4">{
  "name": "required|string|max:255",
  "email": "required|email",
  "phone": "nullable|string|max:20",
  "dob": "nullable|date"
}</textarea>
                <button class="btn btn-primary btn-lg" id="startImportBtn">
                    ▶️ Start Import
                </button>
            </div>
        </div>

        <!-- Progress Section -->
        <div class="card mb-4" id="progressSection" style="display: none;">
            <div class="card-header">
                <h5>📊 Import Progress</h5>
            </div>
            <div class="card-body">
                <div class="progress mb-3" style="height: 25px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" 
                         id="progressBar" role="progressbar" style="width: 0%">0%</div>
                </div>
                <div class="row text-center">
                    <div class="col-md-4">
                        <h6>Total Rows</h6>
                        <h3 id="totalRows">0</h3>
                    </div>
                    <div class="col-md-4">
                        <h6>Processed</h6>
                        <h3 id="processedRows">0</h3>
                    </div>
                    <div class="col-md-4">
                        <h6>Failed</h6>
                        <h3 id="failedRows" class="text-danger">0</h3>
                    </div>
                </div>
                <div class="mt-3">
                    <span class="badge bg-secondary status-badge" id="statusBadge">Pending</span>
                </div>
                <div class="mt-3">
                    <button class="btn btn-info" id="refreshBtn">🔄 Refresh</button>
                    <button class="btn btn-warning" id="retryBtn" style="display: none;">🔁 Retry Failed</button>
                    <a class="btn btn-secondary" id="downloadErrorsBtn" style="display: none;" download>📥 Download Errors</a>
                </div>
            </div>
        </div>

        <!-- Jobs List -->
        <div class="card">
            <div class="card-header">
                <h5>📋 Previous Imports</h5>
            </div>
            <div class="card-body">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>File Name</th>
                            <th>Status</th>
                            <th>Progress</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="jobsTableBody">
                        <tr><td colspan="6" class="text-center">No imports yet</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentJobId = null;
        let currentMapping = {};
        let availableFields = [];
        let fieldSuggestions = {};

        // File Upload Handling
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');

        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('dragover');
        });

        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('dragover');
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('dragover');
            const files = e.dataTransfer.files;
            if (files.length) handleFileUpload(files[0]);
        });

        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length) handleFileUpload(e.target.files[0]);
        });

        async function handleFileUpload(file) {
            const formData = new FormData();
            formData.append('file', file);

            document.getElementById('uploadStatus').innerHTML = 
                '<div class="alert alert-info">Uploading...</div>';

            try {
                const response = await fetch('/api/import/upload', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>'
                    },
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    currentJobId = result.data.job_id;
                    availableFields = result.data.available_fields;
                    fieldSuggestions = result.data.field_suggestions || {};
                    currentMapping = result.data.auto_mapping;
                    
                    document.getElementById('uploadStatus').innerHTML = 
                        `<div class="alert alert-success">File uploaded! ${result.data.total_rows} rows found.</div>`;
                    
                    showMappingSection(result.data.headers, result.data.auto_mapping);
                } else {
                    document.getElementById('uploadStatus').innerHTML = 
                        `<div class="alert alert-danger">${result.message}</div>`;
                }
            } catch (error) {
                document.getElementById('uploadStatus').innerHTML = 
                    `<div class="alert alert-danger">Upload failed: ${error.message}</div>`;
            }
        }

        function showMappingSection(headers, autoMapping) {
            const container = document.getElementById('mappingContainer');
            container.innerHTML = '';

            headers.forEach((header, index) => {
                const mappedField = autoMapping[header] || '';
                const suggestedFields = fieldSuggestions[header] || [];
                const dropdownFields = suggestedFields.length ? suggestedFields : availableFields;
                const row = document.createElement('div');
                row.className = 'mapping-row';
                row.innerHTML = `
                    <strong>${header}</strong>
                    <span>→</span>
                    <input class="form-control form-control-sm mapping-field-input"
                           style="width: 220px;"
                           data-header="${header}"
                           list="mapping-options-${index}"
                           value="${mappedField}">
                    <datalist id="mapping-options-${index}">
                        <option value="">-- Select Field --</option>
                        ${dropdownFields.map(field => 
                            `<option value="${field}">${field}</option>`
                        ).join('')}
                    </datalist>
                `;
                container.appendChild(row);
            });

            document.getElementById('mappingSection').style.display = 'block';
            document.getElementById('importSection').style.display = 'block';
        }

        // Save Mapping
        document.getElementById('saveMappingBtn').addEventListener('click', async () => {
            const selects = document.querySelectorAll('#mappingContainer .mapping-field-input');
            const mapping = {};
            
            selects.forEach(select => {
                if (select.value) {
                    mapping[select.dataset.header] = select.value.trim().toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
                }
            });

            try {
                const response = await fetch('/api/import/mapping', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>'
                    },
                    body: JSON.stringify({
                        job_id: currentJobId,
                        mapping: mapping
                    })
                });

                const result = await response.json();

                if (result.success) {
                    alert('Mapping saved!');
                } else {
                    alert('Mapping failed: ' + result.message);
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        });

        // Start Import
        document.getElementById('startImportBtn').addEventListener('click', async () => {
            try {
                let validationRules = {};
                const validationRulesInput = document.getElementById('validationRulesInput').value.trim();

                if (validationRulesInput) {
                    validationRules = JSON.parse(validationRulesInput);
                }

                const response = await fetch('/api/import/start', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>'
                    },
                    body: JSON.stringify({
                        job_id: currentJobId,
                        validation_rules: validationRules
                    })
                });

                const result = await response.json();

                if (result.success) {
                    document.getElementById('progressSection').style.display = 'block';
                    startPolling();
                } else {
                    alert('Failed to start: ' + result.message);
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        });

        // Poll for status
        let pollingInterval = null;

        function startPolling() {
            if (pollingInterval) clearInterval(pollingInterval);
            pollingInterval = setInterval(checkStatus, 2000);
        }

        async function checkStatus() {
            if (!currentJobId) return;

            try {
                const response = await fetch(`/api/import/status/${currentJobId}`);
                const result = await response.json();

                if (result.success) {
                    updateProgress(result.data);
                    loadJobs();

                    if (result.data.status === 'completed' || result.data.status === 'failed') {
                        clearInterval(pollingInterval);
                        document.getElementById('retryBtn').style.display = 'inline-block';
                        
                        if (result.data.failed_rows > 0) {
                            const errorsResponse = await fetch(`/api/import/errors/${currentJobId}`);
                            const errorsResult = await errorsResponse.json();
                            if (errorsResult.success && errorsResult.total > 0) {
                                document.getElementById('downloadErrorsBtn').style.display = 'inline-block';
                            }
                        }
                    }
                }
            } catch (error) {
                console.error('Status check failed:', error);
            }
        }

        function updateProgress(data) {
            document.getElementById('progressBar').style.width = data.progress_percentage + '%';
            document.getElementById('progressBar').textContent = data.progress_percentage + '%';
            document.getElementById('totalRows').textContent = data.total_rows;
            document.getElementById('processedRows').textContent = data.processed_rows;
            document.getElementById('failedRows').textContent = data.failed_rows;
            
            const statusBadge = document.getElementById('statusBadge');
            statusBadge.textContent = data.status.toUpperCase();
            statusBadge.className = 'badge status-badge ' + 
                (data.status === 'completed' ? 'bg-success' : 
                 data.status === 'failed' ? 'bg-danger' : 
                 data.status === 'processing' ? 'bg-primary' : 'bg-secondary');
        }

        // Refresh button
        document.getElementById('refreshBtn').addEventListener('click', () => {
            if (currentJobId) {
                checkStatus();
            }

            loadJobs();
        });

        // Load jobs list
        async function loadJobs() {
            try {
                const response = await fetch('/api/import/jobs');
                const result = await response.json();

                if (result.success) {
                    const tbody = document.getElementById('jobsTableBody');
                    if (result.data.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="6" class="text-center">No imports yet</td></tr>';
                    } else {
                        tbody.innerHTML = result.data.map(job => `
                            <tr>
                                <td>${job.id}</td>
                                <td>${job.file_name}</td>
                                <td><span class="badge bg-${job.status === 'completed' ? 'success' : job.status === 'failed' ? 'danger' : 'primary'}">${job.status}</span></td>
                                <td>${job.processed_rows}/${job.total_rows}</td>
                                <td>${new Date(job.created_at).toLocaleDateString()}</td>
                                <td>
                                    <button class="btn btn-sm btn-info" onclick="viewJob(${job.id})">View</button>
                                </td>
                            </tr>
                        `).join('');
                    }
                }
            } catch (error) {
                console.error('Failed to load jobs:', error);
            }
        }

        function viewJob(jobId) {
            currentJobId = jobId;
            document.getElementById('progressSection').style.display = 'block';
            checkStatus();
            startPolling();
        }

        // Initial load
        loadJobs();
    </script>
</body>
</html>
<?php /**PATH C:\Users\Dell\Music\Smart_Data_Import_Engine\resources\views/welcome.blade.php ENDPATH**/ ?>