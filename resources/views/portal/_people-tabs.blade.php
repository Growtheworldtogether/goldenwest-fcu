<nav class="tabs" aria-label="Sections">
    <a href="{{ route('beneficiaries.index') }}" @if (request()->routeIs('beneficiaries.*')) aria-current="page" @endif>Beneficiaries</a>
    <a href="{{ route('joint.index') }}" @if (request()->routeIs('joint.*')) aria-current="page" @endif>Joint accounts</a>
</nav>
