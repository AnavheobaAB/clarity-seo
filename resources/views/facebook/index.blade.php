<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facebook Connect</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
        <div class="mb-6 text-center">
            <h1 class="text-2xl font-bold text-gray-800">Facebook Integration</h1>
            <p class="text-gray-600 mt-2">Connect your business page to manage reviews</p>
        </div>

        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline">{{ session('error') }}</span>
            </div>
        @endif

        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline">{{ session('success') }}</span>
            </div>
        @endif

        <div class="flex flex-col gap-4">
            @if(isset($tenant))
                <div class="bg-blue-50 p-4 rounded text-sm text-blue-800 mb-4">
                    <strong>Active Tenant:</strong> {{ $tenant->name }}
                </div>
            @else
                <div class="bg-yellow-50 p-4 rounded text-sm text-yellow-800 mb-4">
                    No tenant found. Please run setup scripts first.
                </div>
            @endif

            <a href="{{ route('facebook.auth') }}" class="w-full bg-[#1877F2] hover:bg-[#166fe5] text-white font-bold py-3 px-4 rounded flex items-center justify-center gap-2 transition duration-200">
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.962.925-1.962 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                </svg>
                Connect with Facebook
            </a>
            
            <div class="text-xs text-gray-500 text-center mt-4">
                This will redirect you to Facebook to authorize permissions.
            </div>
        </div>
    </div>
</body>
</html>
