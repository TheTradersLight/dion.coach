<nav class="navbar navbar-expand-lg navbar-dark sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="/">
            <img src="/assets/logo_mark.png" alt="The traders' light" height="100" width="100">
            <span class="fw-semibold">The traders' light</span>
        </a>

        <!-- Bouton mobile -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Menu -->
        <div class="collapse navbar-collapse justify-content-end" id="mainNav">
            <ul class="navbar-nav align-items-lg-center gap-lg-3">

                <li class="nav-item">
                    <a class="nav-link" href="/news">News</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="/strategies">Strategies</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="/indicators">Indicators</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="/aboutus">About us</a>
                </li>

                <?php if (!empty($_SESSION['user_email'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/camps/evaluate">Camps</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-warning" href="/logout">Logout</a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link text-warning" href="/login">Login</a>
                    </li>
                <?php endif; ?>

            </ul>
        </div>
    </div>
</nav>
