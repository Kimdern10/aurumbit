@php($site = \App\Models\Setting::get('site_name'))
@php($email = \App\Models\Setting::get('support_email'))
<x-legal-page :title="__('Privacy policy')" :intro="__('What personal information we collect, why, and your rights. Written for customers in the United States.')">
    <h2>What we collect</h2>
    <ul>
        <li><strong>Account details:</strong> your name, email address and a scrambled (hashed) password.</li>
        <li><strong>Identity documents</strong> you upload for verification: the document photo, a selfie and your full name.</li>
        <li><strong>Activity in your account:</strong> deposits and their transaction IDs, plans, withdrawals, the Bitcoin addresses you enter and the audit log of balance changes.</li>
        <li><strong>Security data:</strong> your IP address and browser, to keep your account and the website safe.</li>
    </ul>

    <h2>Why we use it</h2>
    <ul>
        <li>To run your account and show you your balances and history.</li>
        <li>To check your identity and help prevent fraud and money laundering.</li>
        <li>To send emails you need, such as email verification and password resets.</li>
    </ul>
    <p>We do <strong>not</strong> sell your information.</p>

    <h2>Who can see it</h2>
    <p>Only {{ $site }} staff with admin access. Identity documents are stored privately on our server, never in a public folder. Market prices are fetched from CoinGecko; no personal data is sent to them.</p>

    <h2>How long we keep it</h2>
    <p>We keep account and audit records while your account is open and for as long as the law requires afterwards. You can delete your account in Settings; identity documents are deleted with it.</p>

    <h2>Your rights</h2>
    <p>You can ask to see, correct or delete the personal information we hold about you by emailing <a href="mailto:{{ $email }}">{{ $email }}</a>. We will reply within 45 days. We will not treat you differently for using these rights.</p>

    <h2>California residents</h2>
    <p>If you live in California, the California Consumer Privacy Act (CCPA) gives you the right to know what personal information we collect and why, to ask us to delete or correct it, and to opt out of its sale or sharing. We do not sell or share personal information for advertising.</p>

    <h2>Children</h2>
    <p>Our service is only for adults aged 18 or over. We do not knowingly collect information from children under 13.</p>

    <h2>Cookies</h2>
    <p>We only use the cookies needed to keep you logged in and protect forms. There are no advertising cookies.</p>
</x-legal-page>
