<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Facebook Page</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-lg">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800 text-center">Select a Page</h1>
            <p class="text-gray-600 mt-2 text-center">Choose which Facebook Page you want to connect.</p>
        </div>

        <form action="{{ route('facebook.save') }}" method="POST">
            @csrf
            <input type="hidden" name="user_access_token" value="{{ $userAccessToken }}">
            
            <div class="space-y-3 max-h-96 overflow-y-auto mb-6">
                @foreach($pages as $page)
                    <div class="relative">
                        <input type="radio" name="page_id" id="page_{{ $page['id'] }}" value="{{ $page['id'] }}" class="peer hidden" required>
                        <input type="hidden" name="page_access_token" value="{{ $page['access_token'] }}" disabled> <!-- Enabled by JS when selected -->
                        
                        <label for="page_{{ $page['id'] }}" class="block p-4 border border-gray-200 rounded cursor-pointer hover:bg-gray-50 peer-checked:border-blue-500 peer-checked:bg-blue-50 peer-checked:ring-1 peer-checked:ring-blue-500 transition">
                            <div class="font-semibold text-gray-800">{{ $page['name'] }}</div>
                            <div class="text-sm text-gray-500">ID: {{ $page['id'] }}</div>
                            <div class="text-xs text-gray-400 mt-1">{{ $page['category'] ?? 'Business' }}</div>
                        </label>
                    </div>
                @endforeach
            </div>

            <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-4 rounded transition duration-200">
                Connect Selected Page
            </button>
        </form>
    </div>

    <script>
        // Simple script to ensure the correct page access token is sent
        document.querySelectorAll('input[name="page_id"]').forEach(radio => {
            radio.addEventListener('change', function() {
                // Disable all hidden token inputs
                document.querySelectorAll('input[name="page_access_token"]').forEach(input => input.disabled = true);
                // Enable only the one next to the selected radio
                this.parentElement.querySelector('input[name="page_access_token"]').disabled = false;
            });
        });
    </script>
</body>
</html>
