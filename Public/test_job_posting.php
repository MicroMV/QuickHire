<!DOCTYPE html>
<html>
<head>
    <title>Test Job Posting</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, textarea, select { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        button { padding: 10px 20px; background: #007cba; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .result { margin-top: 20px; padding: 10px; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .debug { background: #f8f9fa; border: 1px solid #dee2e6; margin-top: 10px; padding: 10px; font-family: monospace; white-space: pre-wrap; }
    </style>
</head>
<body>
    <h1>Test Job Posting</h1>
    
    <div id="authStatus">Checking authentication...</div>
    
    <form id="testForm" style="display: none;">
        <div class="form-group">
            <label>Job Title *</label>
            <input type="text" name="title" value="Test Developer Position" required>
        </div>
        
        <div class="form-group">
            <label>Job Description *</label>
            <textarea name="description" rows="4" required>We are looking for a skilled developer to join our team. This is a test job posting to debug the system.</textarea>
        </div>
        
        <div class="form-group">
            <label>Role Title</label>
            <select name="role_title">
                <option value="">Select Role</option>
                <option value="Software Developer">Software Developer</option>
                <option value="Full Stack Developer">Full Stack Developer</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>Employment Type</label>
            <select name="employment_type">
                <option value="">Select Type</option>
                <option value="FULL_TIME">Full-time</option>
                <option value="PART_TIME">Part-time</option>
                <option value="CONTRACT">Contract</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>Country</label>
            <select name="country">
                <option value="">Select Country</option>
                <option value="Remote">Remote</option>
                <option value="United States">United States</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>Rate per Hour (USD)</label>
            <input type="number" name="rate_per_hour" step="0.01" value="50.00">
        </div>
        
        <input type="hidden" name="csrf_token" id="csrfToken">
        
        <button type="submit">Post Test Job</button>
        <button type="button" onclick="runDebugTests()">Run Debug Tests</button>
    </form>
    
    <div id="result"></div>

    <script>
        // Check authentication status
        async function checkAuth() {
            try {
                const response = await fetch('/QuickHire/Public/debug_post_job.php');
                const data = await response.json();
                
                const authDiv = document.getElementById('authStatus');
                if (data.debug.user_logged_in && data.debug.user_role === 'EMPLOYER') {
                    authDiv.innerHTML = `<div class="success">✅ Authenticated as Employer (ID: ${data.debug.user_id})</div>`;
                    document.getElementById('testForm').style.display = 'block';
                    
                    // Get CSRF token
                    const csrfResponse = await fetch('/QuickHire/Public/employer-dashboard.php');
                    const csrfText = await csrfResponse.text();
                    const csrfMatch = csrfText.match(/name="csrf_token" value="([^"]+)"/);
                    if (csrfMatch) {
                        document.getElementById('csrfToken').value = csrfMatch[1];
                    }
                } else {
                    authDiv.innerHTML = `<div class="error">❌ Not authenticated as employer. Please log in as an employer first.</div>`;
                }
                
                document.getElementById('result').innerHTML = `<div class="debug">Debug Info:\n${JSON.stringify(data.debug, null, 2)}</div>`;
            } catch (error) {
                document.getElementById('authStatus').innerHTML = `<div class="error">Error checking auth: ${error.message}</div>`;
            }
        }
        
        // Test job posting
        document.getElementById('testForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const resultDiv = document.getElementById('result');
            
            try {
                resultDiv.innerHTML = '<div>Posting job...</div>';
                
                const response = await fetch('/QuickHire/Public/actions/post_job.php', {
                    method: 'POST',
                    body: formData
                });
                
                console.log('Response status:', response.status);
                console.log('Response headers:', [...response.headers.entries()]);
                
                const text = await response.text();
                console.log('Response text:', text);
                
                if (text.trim().startsWith('<')) {
                    // HTML response - likely a PHP error
                    resultDiv.innerHTML = `
                        <div class="error">❌ Server returned HTML instead of JSON (PHP error)</div>
                        <div class="debug">Response preview: ${text.substring(0, 200)}...</div>
                        <button onclick="window.open('/QuickHire/Public/debug_job_post.php', '_blank')">Debug Info</button>
                    `;
                    return;
                }
                
                try {
                    const data = JSON.parse(text);
                    if (data.ok) {
                        resultDiv.innerHTML = `<div class="success">✅ Job posted successfully! ID: ${data.job_post_id}</div>`;
                    } else {
                        resultDiv.innerHTML = `<div class="error">❌ Error: ${data.error}</div>`;
                    }
                } catch (parseError) {
                    resultDiv.innerHTML = `
                        <div class="error">❌ Invalid JSON response</div>
                        <div class="debug">Raw response: ${text}</div>
                        <div class="debug">Parse error: ${parseError.message}</div>
                    `;
                }
                
            } catch (error) {
                resultDiv.innerHTML = `<div class="error">❌ Network error: ${error.message}</div>`;
            }
        });
        
        // Run debug tests
        async function runDebugTests() {
            const resultDiv = document.getElementById('result');
            resultDiv.innerHTML = '<div>Running debug tests...</div>';
            
            try {
                // Test 1: Check database table
                const tableResponse = await fetch('/QuickHire/Public/test_job_table.php');
                const tableData = await tableResponse.json();
                
                let debugInfo = 'Database Table Test:\n' + JSON.stringify(tableData, null, 2) + '\n\n';
                
                // Test 2: Check post job debug
                const debugResponse = await fetch('/QuickHire/Public/debug_post_job.php');
                const debugData = await debugResponse.json();
                
                debugInfo += 'Post Job Debug:\n' + JSON.stringify(debugData, null, 2);
                
                resultDiv.innerHTML = `<div class="debug">${debugInfo}</div>`;
                
            } catch (error) {
                resultDiv.innerHTML = `<div class="error">Debug test error: ${error.message}</div>`;
            }
        }
        
        // Initialize
        checkAuth();
    </script>
</body>
</html>