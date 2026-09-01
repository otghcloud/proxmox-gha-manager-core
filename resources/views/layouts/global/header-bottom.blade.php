<header class="navbar-expand-md">
	<div class="collapse navbar-collapse" id="navbar-menu">
		<div class="navbar">
			<div class="container-xl">
				<ul class="navbar-nav">
					<li class="nav-item{{ nav_active('/') }}">
						<a class="nav-link" href="{{ route('dashboard') }}">
							<span class="nav-link-icon d-md-none d-lg-inline-block">
								<i class="fa-solid fa-gauge-high fa-fw"></i>
							</span>
							<span class="nav-link-title">Dashboard</span>
						</a>
					</li>
					<li class="nav-item dropdown{{ nav_active('config*') }}">
						<a aria-expanded="false" class="nav-link dropdown-toggle" data-bs-auto-close="outside" data-bs-toggle="dropdown" href="#" role="button">
							<span class="nav-link-icon d-md-none d-lg-inline-block">
								<i class="fa-solid fa-sliders fa-fw"></i>
							</span>
							<span class="nav-link-title">Config</span>
						</a>
						<div class="dropdown-menu">
							<a class="dropdown-item{{ nav_active('config/environments*') }}" href="{{ route('environments.index') }}">
								<i class="fa-solid fa-diagram-project fa-fw"></i>Environments
							</a>
							<a class="dropdown-item{{ nav_active('config/github-accounts*') }}" href="{{ route('github-accounts.index') }}">
								<i class="fa-brands fa-github fa-fw"></i>GitHub Accounts
							</a>
							<a class="dropdown-item{{ nav_active('config/nodes*') }}" href="{{ route('nodes.index') }}">
								<i class="fa-solid fa-server fa-fw"></i>Nodes
							</a>
						</div>
					</li>

					<li class="nav-item dropdown{{ nav_active('images*') }}">
						<a aria-expanded="false" class="nav-link dropdown-toggle" data-bs-auto-close="outside" data-bs-toggle="dropdown" href="#" role="button">
							<span class="nav-link-icon d-md-none d-lg-inline-block">
								<i class="fa-solid fa-layer-group fa-fw"></i>
							</span>
							<span class="nav-link-title">Images</span>
						</a>
						<div class="dropdown-menu">
							<a class="dropdown-item{{ nav_active('images/builds*') }}" href="{{ route('builds.index') }}">
								<i class="fa-solid fa-hammer fa-fw"></i>Builds
							</a>
							<a class="dropdown-item{{ nav_active('images/pools*') }}" href="{{ route('pools.index') }}">
								<i class="fa-solid fa-tags fa-fw"></i>Pools
							</a>
							<a class="dropdown-item{{ nav_active('images/templates*') }}" href="{{ route('templates.index') }}">
								<i class="fa-solid fa-clone fa-fw"></i>Templates
							</a>
						</div>
					</li>

					<li class="nav-item dropdown{{ nav_active('workflows*') }}">
						<a aria-expanded="false" class="nav-link dropdown-toggle" data-bs-auto-close="outside" data-bs-toggle="dropdown" href="#" role="button">
							<span class="nav-link-icon d-md-none d-lg-inline-block">
								<i class="fa-solid fa-diagram-project fa-fw"></i>
							</span>
							<span class="nav-link-title">Workflows</span>
						</a>
						<div class="dropdown-menu">
							<a class="dropdown-item{{ nav_active('workflows/jobs*') }}" href="{{ route('jobs.index') }}">
								<i class="fa-solid fa-list-check fa-fw"></i>Jobs
							</a>
							<a class="dropdown-item{{ nav_active('workflows/runners*') }}" href="{{ route('runners.index') }}">
								<i class="fa-solid fa-robot fa-fw"></i>Runners
							</a>
						</div>
					</li>

					<li class="nav-item{{ nav_active('settings*') }}">
						<a class="nav-link" href="{{ route('settings.overview') }}">
							<span class="nav-link-icon d-md-none d-lg-inline-block">
								<i class="fa-solid fa-gear fa-fw"></i>
							</span>
							<span class="nav-link-title">Settings</span>
						</a>
					</li>
				</ul>
			</div>
		</div>
	</div>
</header>
