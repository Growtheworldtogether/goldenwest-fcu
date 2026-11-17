@php($updated = \App\Services\CryptoRates::lastUpdated())
<p class="fine">Crypto wallets here are a simulation. No real cryptocurrency is held, traded or sent. Dollar values use the latest price we have @if ($updated) from <a class="plink" href="https://www.coingecko.com/" rel="noopener" target="_blank">CoinGecko</a>, updated {{ $updated->diffForHumans() }}, @endif and change as prices move.</p>
