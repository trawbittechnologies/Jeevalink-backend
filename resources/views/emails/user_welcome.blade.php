<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 8px; }
        .header { text-align: center; margin-bottom: 20px; }
        .credentials { background: #f8fafc; padding: 15px; border-radius: 8px; margin: 20px 0; border: 1px solid #e2e8f0; }
        .button { display: inline-block; padding: 10px 20px; background-color: #dc2626; color: #ffffff; text-decoration: none; border-radius: 5px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2 style="color: #dc2626; margin: 0;">Welcome to JeevaLink!</h2>
        </div>
        <p>Hello <strong>{{ $userName }}</strong>,</p>
        <p>An account has been created for you by a JeevaLink volunteer. You can now log in to the platform to manage your profile and blood donation details.</p>
        
        <div class="credentials">
            <p style="margin-top:0;"><strong>Email:</strong> {{ $email }}</p>
            <p style="margin-bottom:0;"><strong>Password:</strong> <span style="font-family: monospace; font-size: 16px;">{{ $password }}</span></p>
        </div>
        
        <p>Please log in using the button below. <strong>We highly recommend changing your password after your first login.</strong></p>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $loginUrl }}" class="button">Login to Your Account</a>
        </div>
        
        <p>Thank you for joining our life-saving community!<br>The JeevaLink Team</p>
    </div>
</body>
</html>
