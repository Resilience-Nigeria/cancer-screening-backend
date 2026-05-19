<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to NCSR</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f4; padding: 20px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 4px; overflow: hidden;">
                    
                    <!-- Header -->
                    <tr>
                        <td style="background-color: #16a34a; padding: 30px 40px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: normal;">
                                Nigerian National Cancer Screening Register
                            </h1>
                        </td>
                    </tr>
                    
                    <!-- Body -->
                    <tr>
                        <td style="padding: 40px;">
                            
                            <p style="margin: 0 0 20px; color: #333333; font-size: 16px; line-height: 1.5;">
                                Dear {{ $user->firstName }} {{ $user->lastName }},
                            </p>
                            
                            <p style="margin: 0 0 20px; color: #333333; font-size: 14px; line-height: 1.6;">
                                Your account has been created for the National Cancer Screening Register system. Below are your login details:
                            </p>
                            
                            <!-- Credentials Table -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin: 30px 0; border: 1px solid #e0e0e0; border-radius: 4px;">
                                <tr>
                                    <td style="padding: 20px; background-color: #f9f9f9;">
                                        
                                        <table width="100%" cellpadding="8" cellspacing="0">
                                            <tr>
                                                <td width="140" style="color: #666666; font-size: 13px; font-weight: bold;">Email:</td>
                                                <td style="color: #333333; font-size: 14px;">{{ $user->email }}</td>
                                            </tr>
                                            <tr>
                                                <td style="color: #666666; font-size: 13px; font-weight: bold;">Password:</td>
                                                <td style="color: #16a34a; font-size: 16px; font-weight: bold; font-family: 'Courier New', monospace;">{{ $password }}</td>
                                            </tr>
                                            <tr>
                                                <td style="color: #666666; font-size: 13px; font-weight: bold;">Role:</td>
                                                <td style="color: #333333; font-size: 14px;">{{ $user->user_role->roleDescription ?? 'User' }}</td>
                                            </tr>
                                            @if($user->facility)
                                            <tr>
                                                <td style="color: #666666; font-size: 13px; font-weight: bold;">Facility:</td>
                                                <td style="color: #333333; font-size: 14px;">{{ $user->facility->facilityName }}</td>
                                            </tr>
                                            @endif
                                        </table>
                                        
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Security Notice -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin: 20px 0; background-color: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
                                <tr>
                                    <td style="padding: 15px 20px;">
                                        <p style="margin: 0; color: #856404; font-size: 13px; line-height: 1.5;">
                                            <strong>Important:</strong> Please change your password after logging in for the first time. Do not share your login credentials with anyone.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Login Button -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin: 30px 0;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $loginUrl }}" style="display: inline-block; padding: 12px 40px; background-color: #16a34a; color: #ffffff; text-decoration: none; border-radius: 4px; font-size: 14px; font-weight: bold;">
                                            Login to NCSR
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style="margin: 20px 0 0; color: #666666; font-size: 13px; line-height: 1.6;">
                                If you have any questions or need assistance, please contact your system administrator.
                            </p>
                            
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="padding: 20px 40px; background-color: #f9f9f9; border-top: 1px solid #e0e0e0; text-align: center;">
                            <p style="margin: 0 0 5px; color: #999999; font-size: 12px;">
                                Nigerian National Cancer Screening Register
                            </p>
                            <p style="margin: 0; color: #999999; font-size: 11px;">
                                This is an automated message. Please do not reply to this email.
                            </p>
                        </td>
                    </tr>
                    
                </table>
            </td>
        </tr>
    </table>
</body>
</html>