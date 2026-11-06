@php($site = \App\Models\Setting::get('site_name'))
<x-legal-page :title="__('Terms of use')" :intro="__('The rules for using this website and your account.')">
    <h2>1. About this service</h2>
    <p>{{ $site }} offers fixed-term Bitcoin, gold and crypto plans with target rates. Target rates are not guaranteed. You fund your account in USDT or another accepted coin, choose plans, and withdraw in USDT, BTC, ETH or SOL to your own wallet.</p>

    <h2>2. Your account</h2>
    <ul>
        <li>You must be at least 18 years old and give true details.</li>
        <li>Keep your password private. We recommend turning on two-step login.</li>
        <li>One account per person. We may ask you to verify your identity (KYC).</li>
        <li>We may suspend an account that breaks these terms or looks fraudulent.</li>
    </ul>

    <h2>3. Plans</h2>
    <p>Each plan lists its term, minimum and maximum amounts and its rate for the full term. Earnings are added daily at that rate and paid to your balance with your capital at the end of the term. Money placed in a plan is locked until then. If we have to close a plan early, your capital is returned to your balance.</p>

    <h2>4. Deposits and withdrawals</h2>
    <ul>
        <li>Your balance is held in USDT. Send only the coin and network shown next to each deposit address in your account, then submit the amount and transaction ID. After the transaction is confirmed on the blockchain, we credit its value in USDT at the market price when we review it.</li>
        <li>Withdrawals are paid in USDT on the TRC20 (Tron) or ERC20 (Ethereum) network and need a valid address of your own on that network. The amount is held from your balance while an admin reviews the request, and returned if it is rejected.</li>
        <li>We may ask for identity verification before a withdrawal.</li>
        <li>Approved withdrawals are sent to the address you gave. Each request has a reference number, and we add the payment's transaction ID when we have it. Payments on the blockchain cannot be reversed.</li>
    </ul>

    <h2>5. Referrals</h2>
    <p>You may share your referral link. A referral bonus is paid only on the first approved deposit of a person you invited. Do not spam or mislead people to get sign-ups.</p>

    <h2>6. No advice</h2>
    <p>Nothing on this website is investment, tax or legal advice. Read the <a href="{{ route('legal.risk') }}" wire:navigate>risk disclosure</a>.</p>

    <h2>7. Changes and contact</h2>
    <p>We may update these terms; the latest version is always on this page. Questions: <a href="mailto:{{ \App\Models\Setting::get('support_email') }}">{{ \App\Models\Setting::get('support_email') }}</a>.</p>
</x-legal-page>
