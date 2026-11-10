<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <!-- REQUIRED META TAGS -->
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- CUSTOM STYLE -->
    <link rel="icon" type="image" href="{{ asset('images/required/theme-favicon-logo.png') }}">
    <link rel="stylesheet" href="{{ asset('themes/default/fonts/fontawesome/fontawesome.css') }}">
    <link rel="stylesheet" href="{{ asset('themes/default/css/custom.css') }}">

    @vite('resources/css/app.css')

    <title>@if (trim($__env->yieldContent('template_title')))@yield('template_title')
        | @endif {{ trans('installer.title') }}</title>
    <script>
        window.Laravel = <?php echo json_encode([
            'csrfToken' => csrf_token(),
        ]); ?>
    </script>
</head>
<body class="bg-installer bg-no-repeat bg-cover bg-center">
<div id="step-group" class="w-screen h-dvh  overflow-y-auto p-3 sm:p-10">
    <div id="steps" class="block w-[550px] mx-auto overflow-hidden rounded-xl shadow-paper p-8 bg-white">
        <h3 class="text-lg font-semibold capitalize text-center mb-7">@yield('title')</h3>
        @yield('container')
    </div>
</div>

<script src="{{ asset('themes/default/js/jquery-v3.7.1.min.js') }}"></script>
<script src="{{ asset('themes/default/js/jqueryScript.js') }}"></script>
<script src="{{ asset('themes/default/js/installer.js') }}"></script>
</body>
</html>
