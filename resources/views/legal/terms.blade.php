@php($site = \App\Models\Setting::get('site_name'))
<x-legal-page :title="__('Terms of use')" :intro="__('The rules for using this website and your account.')">
    <h2>1. About this service</h2>
    <p>{{ $site }} is a demonstration of an investment platform for Bitcoin and gold mining plans. In demo mode, all balances, deposits, investments, earnings and withdrawals are simulated. No real funds are accepted, held or paid out, and nothing you see creates a claim to money or assets.</p>

    <h2>2. Your account</h2>
    <ul>
        <li>You must be at least 18 years old and give true details.</li>
        <li>Keep your password private. We recommend turning on two-step login.</li>
        <li>One account per person. We may ask you to verify your identity (KYC).</li>
        <li>We may suspend an account that breaks these terms or looks fraudulent.</li>
    </ul>

    <h2>3. Plans</h2>
    <p>Each plan lists its term, minimum and maximum amounts, a risk level and an indicative target. The target is used only to simulate earnings and is <strong>not guaranteed</strong>. Money placed in a plan is locked until the end of its term. An admin may close a plan early, in which case the principal is returned to your balance.</p>

    <h2>4. Deposits and withdrawals</h2>
    <ul>
        <li>Deposits shown on this site are simulated and credited by an admin after review.</li>
        <li>Withdrawals need a valid Bitcoin address of your own. The amount is held from your balance while an admin reviews the request, and returned if it is rejected.</li>
        <li>We may ask for identity verification before a withdrawal.</li>
        <li>In demo mode, approved withdrawals are not sent on-chain.</li>
    </ul>

    <h2>5. Referrals</h2>
    <p>You may share your referral link. Any referral bonus shown is simulated, small, and only paid on the first approved deposit of a person you invited. Do not spam or mislead people to get sign-ups.</p>

    <h2>6. No advice</h2>
    <p>Nothing on this website is investment, tax or legal advice. Read the <a href="{{ route('legal.risk') }}">risk disclosure</a>.</p>

    <h2>7. Changes and contact</h2>
    <p>We may update these terms; the latest version is always on this page. Questions: <a href="mailto:{{ \App\Models\Setting::get('support_email') }}">{{ \App\Models\Setting::get('support_email') }}</a>.</p>
</x-legal-page>
