<!DOCTYPE html>
<html>
<head>
    <title>Test Parent Categories</title>
</head>
<body>
    <h1>Test Parent Categories</h1>
    
    <select id="type">
        <option value="service_experience">Service & Experience</option>
        <option value="providers">Providers</option>
    </select>
    
    <button onclick="loadCategories()">Load Categories</button>
    
    <select id="categories">
        <option value="">Select a Category</option>
    </select>
    
    <pre id="result"></pre>
    
    <script>
        function loadCategories() {
            const typeSelect = document.getElementById('type');
            const categoriesSelect = document.getElementById('categories');
            const resultDiv = document.getElementById('result');
            const selectedType = typeSelect.value;
            
            // Clear current options except the first one
            while (categoriesSelect.options.length > 1) {
                categoriesSelect.remove(1);
            }
            
            fetch('/category/get-parent-categories?type=' + selectedType)
                .then(response => response.json())
                .then(data => {
                    resultDiv.textContent = JSON.stringify(data, null, 2);
                    
                    if (data.success) {
                        data.categories.forEach(category => {
                            const option = document.createElement('option');
                            option.value = category.id;
                            option.textContent = category.name;
                            
                            if (category.level > 0) {
                                option.textContent = '- '.repeat(category.level) + option.textContent;
                            }
                            
                            categoriesSelect.appendChild(option);
                        });
                    }
                })
                .catch(error => {
                    resultDiv.textContent = 'Error: ' + error.message;
                });
        }
        
        // Load categories on page load
        document.addEventListener('DOMContentLoaded', loadCategories);
    </script>
</body>
</html> 