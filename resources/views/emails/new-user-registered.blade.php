<div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <h2>{{ $user->email }} from {{config('app.name')}}</h2>

    <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
        <tr style="background-color: #f5f5f5;">
            <td style="padding: 10px; border: 1px solid #ddd; font-weight: bold; width: 150px;">Name:</td>
            <td style="padding: 10px; border: 1px solid #ddd;">{{ $user->name }}</td>
        </tr>
        <tr>
            <td style="padding: 10px; border: 1px solid #ddd; font-weight: bold;">Email:</td>
            <td style="padding: 10px; border: 1px solid #ddd;">{{ $user->email }}</td>
        </tr>
        <tr style="background-color: #f5f5f5;">
            <td style="padding: 10px; border: 1px solid #ddd; font-weight: bold;">Phone:</td>
            <td style="padding: 10px; border: 1px solid #ddd;">{{ $user->phone ?? 'Not provided' }}</td>
        </tr>
        <tr>
            <td style="padding: 10px; border: 1px solid #ddd; font-weight: bold;">Registered At:</td>
            <td style="padding: 10px; border: 1px solid #ddd;">{{ $user->created_at->format('F d, Y \a\t g:i A') }}</td>
        </tr>
    </table>

    <p style="color: #666; font-size: 12px; margin-top: 20px;">
        This is an automated notification from {{config('app.name')}}.
    </p>
</div>
