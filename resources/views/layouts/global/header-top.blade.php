<header class="navbar navbar-expand-md d-print-none bg-primary" data-bs-theme="dark">
	<div class="container-xl">
		<button aria-controls="navbar-menu" aria-expanded="false" aria-label="Toggle navigation" class="navbar-toggler" data-bs-target="#navbar-menu" data-bs-toggle="collapse" type="button">
			<span class="navbar-toggler-icon"></span>
		</button>

		<div class="navbar-brand navbar-brand-autodark d-none-navbar-horizontal pe-0 pe-md-3" data-bs-theme="dark">
			<a aria-label="OTGH Cloud" href="{{ route('dashboard') }}">
				<img src="{{ asset('default_logo.png') }}" width="100" alt="OTGH Cloud" />
			</a>
		</div>

		<div class="navbar-nav flex-row order-md-last">
			<div class="nav-item dropdown">
				<a aria-label="Open user menu" class="nav-link d-flex lh-1 p-0 px-2" data-bs-toggle="dropdown" href="#">
					<span class="avatar avatar-sm">
						<i class="fa-regular fa-user"></i>
					</span>
					<div class="d-none d-xl-block ps-2">
						<div>{{ auth()->user()?->name }}</div>
						<div class="mt-1 small text-secondary">Administrator</div>
					</div>
				</a>
				<div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
					<a class="dropdown-item" href="{{ route('settings.index') }}">Settings</a>
					<div class="dropdown-divider"></div>
					<form action="{{ route('logout') }}" method="POST">
						@csrf
						<button class="dropdown-item" type="submit">Log out</button>
					</form>
				</div>
			</div>
		</div>
	</div>
</header>
