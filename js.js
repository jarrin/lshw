/* UI logic for file upload and alerts */
document.addEventListener('DOMContentLoaded', () => {
    const uploadArea = document.getElementById('uploadArea');
    const fileInput = document.getElementById('fileInput');
    const alertBox = document.getElementById('alertBox');

    if (!uploadArea || !fileInput) return;

    uploadArea.addEventListener('click', () => fileInput.click());

    uploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadArea.classList.add('dragover');
    });

    uploadArea.addEventListener('dragleave', () => {
        uploadArea.classList.remove('dragover');
    });

    uploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadArea.classList.remove('dragover');
        handleFiles(e.dataTransfer.files);
    });

    fileInput.addEventListener('change', (e) => handleFiles(e.target.files));

    function handleFiles(files) {
        const validFiles = Array.from(files).filter(f => true); // accept all; server validates
        if (validFiles.length === 0) {
            showAlert('Geen bestanden gevonden', 'error');
            return;
        }
        uploadFiles(validFiles);
    }

    function uploadFiles(files) {
        const formData = new FormData();
        files.forEach(file => formData.append('xml_file[]', file));

        showAlert('⏳ Bestanden worden geüpload...', 'info');

        fetch('', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showAlert('✓ ' + data.success_count + ' bestanden succesvol geïmporteerd', 'success');
                    setTimeout(() => location.reload(), 1200);
                } else {
                    let msg = '✗ Fout bij importeren\n';
                    if (data.errors && data.errors.length) {
                        msg += data.errors.join('\n');
                    } else if (data.message) {
                        msg += data.message;
                    }
                    showAlert(msg, 'error');
                }
            })
            .catch(e => {
                showAlert('✗ Verbindingsfout: ' + e.message, 'error');
            });
    }

    function showAlert(message, type) {
        const className = type === 'success' ? 'alert-success' : type === 'error' ? 'alert-error' : 'alert-info';
        if (!alertBox) return;
        alertBox.innerHTML = `<div class="alert ${className}">${message.replace(/\n/g, '<br>')}</div>`;
    }
});