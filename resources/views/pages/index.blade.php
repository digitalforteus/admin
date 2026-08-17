<?php

use App\Routes\Web;

?>
<x-main>
    <x-status-toast/>

    <nav aria-label="Client and contact links" class="mx-auto grid max-w-6xl gap-4 px-6 pb-16 lg:grid-cols-2 lg:px-10 lg:pb-20">
        <a href="{{Web::login->value}}" class="group border border-base-300 bg-base-100 p-6 hover:border-primary">
            <span class="text-lg font-semibold text-primary group-hover:underline">Login</span>
            <span class="mt-2 block text-sm leading-relaxed text-base-content/70">
                Sign in securely to access your client account, software services, and API credentials.
            </span>
        </a>
        <a href="{{Web::contact->value}}" class="group border border-base-300 bg-base-100 p-6 hover:border-primary">
            <span class="text-lg font-semibold text-primary group-hover:underline">Contact</span>
            <span class="mt-2 block text-sm leading-relaxed text-base-content/70">
                Talk with us about automation, consulting, custom development, or account support.
            </span>
        </a>
    </nav>
</x-main>
