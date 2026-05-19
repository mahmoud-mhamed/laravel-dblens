<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login · DbLens</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>.mono{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace}</style>
</head>
<body class="bg-slate-100">
<div class="min-h-screen flex items-center justify-center p-5">
    <div class="bg-white border border-slate-200 rounded-lg shadow-lg p-10 w-full max-w-sm">
        <div class="flex items-center justify-center gap-2 mb-8">
            <span class="text-2xl">🔍</span>
            <span class="text-xl font-bold text-slate-800">DbLens</span>
        </div>

        @if ($errors->any())
            <div class="mb-4 px-3 py-2 bg-red-50 border border-red-200 text-red-700 rounded text-sm">
                {{ $errors->first('password') }}
            </div>
        @endif

        <form method="POST" action="{{ route('dblens.login.submit') }}">
            @csrf
            <label for="password" class="block text-xs font-semibold text-slate-600 mb-2">Password</label>
            <input id="password" type="password" name="password" required autofocus
                   placeholder="Enter password"
                   class="w-full px-3 py-2 border border-slate-300 rounded focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100 text-sm">
            <button type="submit"
                    class="w-full mt-5 px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded text-sm font-semibold">
                Login
            </button>
        </form>
    </div>
</div>
</body>
</html>
