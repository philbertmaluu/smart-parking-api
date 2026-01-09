import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

if (typeof window !== 'undefined') {
    window.Pusher = Pusher;

    window.Echo = new Echo({
        broadcaster: 'reverb',
        // Next.js uses process.env.NEXT_PUBLIC_ instead of import.meta.env
        key: process.env.NEXT_PUBLIC_REVERB_APP_KEY,
        wsHost: process.env.NEXT_PUBLIC_REVERB_HOST,
        wsPort: process.env.NEXT_PUBLIC_REVERB_PORT || 8080,
        wssPort: process.env.NEXT_PUBLIC_REVERB_PORT || 8080,
        forceTLS: process.env.NEXT_PUBLIC_REVERB_SCHEME === 'https',
        enabledTransports: ['ws', 'wss'],
        
        /**
         * FIX FOR THE 404 ERROR:
         * We must explicitly tell Echo where the broadcast auth endpoint is
         * since your API has a custom prefix (/api/toll-v1).
         */
        authEndpoint: `${process.env.NEXT_PUBLIC_API_URL}/broadcasting/auth`,
        auth: {
            headers: {
                // This ensures the server knows who is trying to listen
                Authorization: `Bearer ${localStorage.getItem('token')}`, 
                Accept: 'application/json',
            },
        },
    });
}