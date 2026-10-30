@php($site = \App\Models\Setting::get('site_name'))
<x-legal-page :title="__('Risk disclosure')" :intro="__('Please read this before you put money into any crypto-asset or mining plan, here or anywhere else.')">
    <h2>{{ __('You can lose all the money you invest') }}</h2>
    <p>Bitcoin and other crypto-assets are highly volatile. Their price can fall sharply and quickly, and may go to zero. Gold prices also rise and fall. Any plan linked to these assets can lose value.</p>

    <h2>How plan rates work</h2>
    <p>Each plan on {{ $site }} shows a <strong>rate for its full term</strong>, and earnings are added to your plan every day at that rate. The rate is set from expected mining income, which depends on things nobody fully controls, including:</p>
    <ul>
        <li>the market price of Bitcoin and gold;</li>
        <li>Bitcoin network difficulty and the block reward, which halves roughly every four years;</li>
        <li>energy prices, equipment failure and hosting costs;</li>
        <li>for gold, ore grades, mine operations, permits, weather and security;</li>
        <li>changes in law, tax and regulation.</li>
    </ul>
    <p>If these change badly, the business behind the plans may struggle to pay, and you could lose some or all of your money.</p>

    <h2>Check who you invest with</h2>
    <p>Before investing with any company, check that it is registered with the relevant regulator, such as the Securities and Exchange Commission in Nigeria, and is allowed to deal in digital assets. Our registration details are shown at the bottom of every page.</p>

    <h2>Your money is locked for the term</h2>
    <p>Money placed in a plan cannot be taken out until the term ends. You may not be able to react to price changes during that time.</p>

    <h2>Crypto transfers cannot be reversed</h2>
    <p>A Bitcoin payment sent to the wrong address is usually lost forever. Always check your withdrawal address carefully. We validate its format, but we cannot tell whether it belongs to you.</p>

    <h2>No advice</h2>
    <p>Nothing on this website is financial advice. Speak to an independent, licensed adviser before investing.</p>
</x-legal-page>
