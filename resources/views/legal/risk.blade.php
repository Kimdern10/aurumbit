@php($site = \App\Models\Setting::get('site_name'))
<x-legal-page :title="__('Risk disclosure')" :intro="__('Please read this before you put money into any crypto-asset or mining plan, here or anywhere else.')">
    <h2>{{ __('You can lose all the money you invest') }}</h2>
    <p>Bitcoin and other crypto-assets are highly volatile. Their price can fall sharply and quickly, and may go to zero. Gold prices also rise and fall. Any plan linked to these assets can lose value.</p>

    <h2>Returns are not guaranteed</h2>
    <p>Each plan on {{ $site }} shows an <strong>indicative target</strong>. It is the figure used to simulate earnings in this demo. It is not a promise, a forecast or a fixed rate. Real mining income depends on things nobody controls, including:</p>
    <ul>
        <li>the market price of Bitcoin and gold;</li>
        <li>Bitcoin network difficulty and the block reward, which halves roughly every four years;</li>
        <li>energy prices, equipment failure and hosting costs;</li>
        <li>for gold, ore grades, mine operations, permits, weather and security;</li>
        <li>changes in law, tax and regulation.</li>
    </ul>
    <p>Real results can be lower than the target, nothing at all, or a loss of capital.</p>

    <h2>Watch out for guaranteed-return schemes</h2>
    <p>Be very careful with any website that promises fixed daily or weekly profits, pays you for bringing in new investors, or pressures you to deposit quickly. These are common signs of investment fraud and Ponzi schemes. Check that any company you invest with is registered with the relevant regulator, such as the Securities and Exchange Commission in Nigeria, and is allowed to deal in digital assets.</p>

    <h2>Your money is locked for the term</h2>
    <p>Money placed in a plan cannot be taken out until the term ends. You may not be able to react to price changes during that time.</p>

    <h2>Crypto transfers cannot be reversed</h2>
    <p>A Bitcoin payment sent to the wrong address is usually lost forever. Always check your withdrawal address carefully. We validate its format, but we cannot tell whether it belongs to you.</p>

    <h2>Demo mode</h2>
    <p>{{ $site }} currently runs in demo mode. No real Bitcoin is received, held or sent, and all balances are simulated. Nothing on this website is financial advice. Speak to an independent, licensed adviser before investing.</p>
</x-legal-page>
