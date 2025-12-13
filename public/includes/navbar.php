<nav class="navbar navbar-expand-lg navbar-dark sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="/">
            <img src="/assets/logo_mark.png" alt="dion.coach" height="100" width="100">
            <span class="fw-semibold"></span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse justify-content-end" id="mainNav">
            <ul class="navbar-nav align-items-lg-center">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="menuDrop" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Menu
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="menuDrop">
                        <li><a class="dropdown-item" href="/nouvelles">Nouvelles</a></li>
                        <li><a class="dropdown-item" href="/a-propos">À propos</a></li>
                        <li><a class="dropdown-item" href="/contact">Contactez-moi</a></li>
                        <? if(!empty(($user['email']))){ ?>
                            <li><a class="dropdown-item" href="/logout">Deconnexion</a></li>
                        <? }else{  ?>
                            <li><a class="dropdown-item" href="/login">Connexion</a></li>
                        <? } ?>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
