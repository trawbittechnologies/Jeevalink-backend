<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 8px; }
        .header { text-align: center; margin-bottom: 20px; }
        .otp { font-size: 24px; font-weight: bold; letter-spacing: 5px; color: #dc2626; text-align: center; margin: 20px 0; background: #fee2e2; padding: 15px; border-radius: 8px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2 style="color: #dc2626; margin: 0;">JeevaLink</h2>
        </div>
        <p>Hello <strong>{{ $userName }}</strong>,</p>
        <p>A JeevaLink Volunteer has requested to update your profile details. To authorize this update, please provide the following One-Time Password (OTP) to the volunteer:</p>
        
        <div class="otp">{{ $otp }}</div>
        
        <p>This code will expire in 10 minutes. If you did not authorize this update, please ignore this email and contact support.</p>
        
        <p>Thank you,<br>The JeevaLink Team</p>
    </div>
</body>
</html>
