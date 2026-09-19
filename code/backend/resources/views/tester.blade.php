<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Domain Intelligence Tester</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-10 font-sans">
    <div class="max-w-3xl mx-auto bg-white p-8 rounded-lg shadow-md">
        <h1 class="text-2xl font-bold mb-2">Domain Intelligence - API Tester</h1>
        <p class="text-gray-600 mb-6">Enter a domain below to run the deep analysis pipeline. (Method: POST)</p>
        
        <div class="flex gap-4 mb-4">
            <input type="text" id="domainInput" placeholder="https://www.example.com" class="flex-1 border border-gray-300 p-3 rounded focus:outline-none focus:border-blue-500" value="https://www.example.com">
            <button onclick="runScan()" id="scanBtn" class="bg-blue-600 text-white px-6 py-3 rounded font-semibold hover:bg-blue-700 transition">Start Scan</button>
        </div>

        <div id="loading" class="hidden text-amber-600 font-medium mb-4">
            ⏳ Scanning in progress... Please wait 3 to 5 minutes as it performs deep DNS and HTTP checks. Do not refresh the page.
        </div>

        <div id="resultContainer" class="hidden">
            <h3 class="font-bold text-gray-700 mb-2">JSON Response:</h3>
            <pre id="jsonResult" class="bg-gray-800 text-green-400 p-4 rounded overflow-auto text-sm max-h-96"></pre>
        </div>
    </div>

    <script>
        async function runScan() {
            const domain = document.getElementById('domainInput').value;
            if(!domain) return alert('Please enter a domain');

            const btn = document.getElementById('scanBtn');
            const loading = document.getElementById('loading');
            const resultContainer = document.getElementById('resultContainer');
            const jsonResult = document.getElementById('jsonResult');

            // UI update
            btn.disabled = true;
            btn.classList.add('opacity-50', 'cursor-not-allowed');
            loading.classList.remove('hidden');
            resultContainer.classList.add('hidden');
            jsonResult.textContent = '';

            try {
                // Background POST request
                const response = await fetch('/api/scan', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ domain: domain })
                });

                const data = await response.json();
                jsonResult.textContent = JSON.stringify(data, null, 4);
                resultContainer.classList.remove('hidden');
            } catch (error) {
                jsonResult.textContent = "Error: " + error.message;
                resultContainer.classList.remove('hidden');
            } finally {
                // Reset UI
                btn.disabled = false;
                btn.classList.remove('opacity-50', 'cursor-not-allowed');
                loading.classList.add('hidden');
            }
        }
    </script>
</body>
</html>