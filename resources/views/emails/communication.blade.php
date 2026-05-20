<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>{{ $subject ?? config('app.name') }}</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #1f2937;">
    <div style="max-width: 600px; margin: 0 auto; padding: 24px;">
        {!! nl2br(e($body)) !!}
    </div>
</body>
</html>
