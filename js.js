     const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('fileInput');
        
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
            for (let file of files) {
                if (file.name.endsWith('.xml')) {
                    uploadFile(file);
                }
            }
        }
        
        function uploadFile(file) {
            const formData = new FormData();
            formData.append('xml_file', file);
            
            fetch('', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert('✓ ' + file.name + ' geïmporteerd');
                        location.reload();
                    } else {
                        alert('✗ Fout: ' + data.message);
                    }
                });
        }