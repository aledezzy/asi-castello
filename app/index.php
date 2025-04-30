<!DOCTYPE html>
<html lang="it">

<head>
    <head>
        <meta charset="UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.1/dist/css/bootstrap.min.css"
            integrity="sha384-iYQeCzEYFbKjA/T2uDLTpkwGzCiq6soy8tYaI1GyVh/UjpbCx/TYkiZhlZB6+fzT" crossorigin="anonymous">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <!-- Existing Montserrat/Yellowtail font -->
        <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@200;400;700&family=Yellowtail&display=swap"
            rel="stylesheet">
        <!-- Add the new Cinzel font -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400..900&display=swap" rel="stylesheet">
        <!-- Favicons and other links -->
        <link rel="apple-touch-icon" sizes="180x180" href="images/favicons/apple-touch-icon.png">
        <link rel="icon" type="image/png" sizes="32x32" href="images/favicons/favicon-32x32.png">
        <link rel="icon" type="image/png" sizes="16x16" href="images/favicons/favicon-16x16.png">
        <link rel="stylesheet" type="text/css" href="styles.bundle.css">
        <title>Homepage</title>
    </head>
    
    <title>Homepage</title>
</head>

<body>
    <!-- NavBar -->
    <header>
        <nav class="navGlobal navbar navbar-dark navbar-expand-md fixed-top">
            <div class="container-fluid">
                <a class="navbar-brand" href="">ASI - Club auto d'epoca</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                    aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse navCol" id="navbarNav">
                    <ul class="navbar-nav nav-right">
                        <li class="nav-item">
                            <a class="nav-link active" aria-current="page" href="">Home</a>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link" href="pages\about.html">Chi siamo</a>
                        </li>
                    </ul>
                    <ul class="navbar-nav nav-right ms-auto">
                        <li class="nav-item mx-auto">
                            <a class="btn btn-outline-success" id="login" href="login.php"
                            >Login</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <!-- Hero Section -->
    <section class="container-fluid heroImage" id="heroImage">
        <h1 class="col-12 text-center heroText">ASI - Club auto d'epoca</h1>
    </section>

    <!-- Featured Cars Section -->
    <section class="container-xl pb-4" id="featuredCars">
        <h2 class="display-5 text-center" style="font-family: Cinzel, serif;">Le auto più belle di sempre, una passione che ci unisce</h2>
        <section class="row pt-2">
            <div class="col-md-4 col-12 featuredCarsPara">
                <img src="images/image3.jpg" class="img-thumbnail featuredThumbs" alt="Chevrolet Chevelle SS.">
                <h5 class="text-center">Chevrolet Chevelle SS</h5>
                V8 da 454 pollici cubi, 450 CV, negli anni '70, era una quantità di potenza folle.
                Ammira la pura bontà americana MOPAR in questa Chevy Chevelle SS splendidamente conservata,
                dai gadget del pacchetto SS al suono assordante del V8 big-block.
            </div>

                       <div class="col-md-4 col-12 featuredCarsPara">
                            <img src="images/image4.jpg" class="img-thumbnail featuredThumbs" alt="Porsche 356A Speedster.">
                            <h5 class="text-center">Porsche 356A Speedster</h5>
                            <!-- Traduzione: -->
                            Senti le vere radici del leggendario marchio automobilistico Porsche, con la 356A Speedster. Realizzata negli anni '50, questa vettura presenta un design senza tempo che ha plasmato Porsche per gli anni a venire. Vivi questa 356A in tutto il suo splendore al Vintage Autohaus.
                        </div>
            
                        <div class="col-md-4 col-12 featuredCarsPara">
                            <img src="images/image5.jpg" class="img-thumbnail featuredThumbs" alt="Mercedez 190SL.">
                            <h5 class="text-center">Mercedes-Benz 190SL</h5>
                            <!-- Traduzione: -->
                            Una delle auto più belle mai realizzate, la Mercedes-Benz 190SL è la regina degli anni '50. È uno dei modelli Mercedes più rari e ricercati. Ammirane una in condizioni impeccabili con un pacchetto opzionale per un giro oggi stesso.
                        </div>
                    </section>
                    <section class="row pt-4">
                        <div class="col-md-4 col-12 featuredCarsPara">
                            <img src="images/image6.jpg" class="img-thumbnail featuredThumbs" alt="Shelby Cobra 427.">
                            <h5 class="text-center">Shelby AC Cobra 427</h5>
                            <!-- Traduzione: -->
                            Una delle auto più rare in esposizione,
                            la Shelby Cobra 427 era l'orgoglio e la gioia del leggendario pilota Carrol Shelby.
                            Un V8 da 7 litri e un telaio leggero spingono questa vettura da 0 a 60 mph (circa 96 km/h) in 3,4 secondi,
                            rendendola una delle auto più veloci del suo tempo.
                        </div>
                        <div class="col-md-4 col-12 featuredCarsPara">
                            <img src="images/image7.jpg" class="img-thumbnail featuredThumbs" alt="Audi S1 Sport Quattro.">
                            <h5 class="text-center">Audi S1 Sport Quattro</h5>
                            <!-- Traduzione: -->
                            Immagina: è il 1985. Sei Audi. Vuoi vincere il Rally Gruppo B.
                            Cosa fai? Crei un mostro 4WD da 500 CV. Questa è l'Audi S1.
                            Quest'auto ha plasmato il futuro di Audi, con il notevole sistema di trazione Quattro.
                            Un pezzo di storia, proprio qui, al Vintage Autohaus.
                        </div>
                        <div class="col-md-4 col-12 featuredCarsPara">
                            <img src="images/image8.jpg" class="img-thumbnail featuredThumbs" alt="Ferrari Dino 246 GT.">
                            <h5 class="text-center">Ferrari Dino 246 GT</h5>
                            <!-- Traduzione: -->
                            Una delle pochissime Ferrari con un V6, la Dino 246 GT presentava un design rivoluzionario.
                            È la prima Ferrari "prodotta in serie", con un design e un'esperienza di guida distintivi, grazie al cambio manuale a 5 marce sincronizzato.
                             Con questa, vedere per credere, sicuramente.
                        </div>
            
        </section>
    </section>

    <!-- Call to Action Section -->
    <section class="container-fluid justify-content-center callToAction">
               <!-- Aggiungi 'justify-content-center' a questa riga -->
               <div class="container-xl mx-auto row justify-content-center">
                   <div class="col-md-4 col-12 sectionCTA">
                       <h5 class="text-left headingsCTA">Visualizza gli eventi <abbr title=""></abbr>disponibili</h5>
                          <p>Scopri gli eventi in programma e prenota il tuo posto per un'esperienza indimenticabile.</p>
                       <p><a class="buttonsCTA btn btn-primary" href="manifestazioni.php">Iscriviti ad una manifestazione disponibile <span id="arrow">&#8594</span></a></p>
                   </div>
                   <div class="col-md-4 col-12 sectionCTA">
                        <h5 class="text-left headingsCTA">Diventa Socio ASI</h5>
                        <p>Unisciti a noi per goderti vantaggi esclusivi, eventi e molto altro.</p>
                        <p><a class="buttonsCTA btn btn-primary" href="diventa-socio.php">Tesserati ora <span id="arrow">&#8594</span></a></p>
                   </div>
               </div>
       

        <!-- Upcoming Events Carousel Section -->
        <div id="upcomingEventsSection" class="container-xl mx-auto">
            <h2 id="upcomingEvents" class="display-5 text-center py-4">Esplora i prossimi eventi</h2>
            <div id="upcomingEventsCarousel" class="col-10 mx-auto">
                <div id="carouselExampleCaptions" class="carousel slide" data-bs-ride="carousel">
                    <div class="carousel-indicators">
                        <button type="button" data-bs-target="#carouselExampleDark" data-bs-slide-to="0" class="active"
                            aria-current="true" aria-label="Slide 1"></button>
                        <button type="button" data-bs-target="#carouselExampleDark" data-bs-slide-to="1"
                            aria-label="Slide 2"></button>
                        <button type="button" data-bs-target="#carouselExampleDark" data-bs-slide-to="2"
                            aria-label="Slide 3"></button>
                    </div>
                    <div class="carousel-inner">
                        <div class="carousel-item active" data-bs-interval="3000">
                            <img id="cImage" src="images/image-carousel-1.jpg" class="d-block w-100"
                                alt="Beetle Meet&Greet">
                            <div id="cCaption1" class="carousel-caption">
                                <h5>2025 Beetle Meet&Greet</h5>
                                <p id="cCaptionPara">Il nostro annuale incontro e saluto per le Beetle è qui! Goditi snack e bevande con una piccola quota</p>
                                <p id="dateStamp">Martedì, 15 Luglio 2025.</p>
                                <a class="carouselButton btn btn-primary" href="manifestazioni.php">Dettagli</a>
                            </div>
                        </div>
                        <div class="carousel-item" data-bs-interval="3000">
                            <img id="cImage" src="images/image-carousel-2.jpg" class="d-block w-100"
                                alt="'22 VelocityTouge Invitational">
                            <div id="cCaption2" class="carousel-caption">
                                <h5>'25 Ritrovo delle Fiat 500</h5>
                                <p id="cCaptionPara">Unisciti a noi per il nostro annuale incontro delle Fiat 500! Scopri le auto più belle e uniche provenienti da tutto il mondo. Presentato da Shell&reg;.</p>
                                <p id="dateStamp">Domenica, 10 Agosto 2025</p>
                                <a class="carouselButton btn btn-primary" href="manifestazioni.php">Dettagli</a>
                            </div>
                        </div>
                        <div class="carousel-item" data-bs-interval="3000">
                            <img id="cImage" src="images/image-carousel-3.jpg" class="d-block w-100"
                                alt="Mercedes Heritage Gala">
                            <div id="cCaption3" class="carousel-caption">
                                <h5>Mercedes Heritage Gala</h5>
                                <p id="cCaptionPara">Scopri l'incredibile patrimonio di Mercedes-Benz e impara di più sulla loro storia come azienda - con merchandising esclusivo Mercedes!</p>
                                <p id="dateStamp">Domenica, 18 Dicembre, 2025.</p>
                                <a class="carouselButton btn btn-primary" href="manifestazioni.php">Dettagli</a>
                            </div>
                        </div>
                    </div>
                    <button class="carousel-control-prev" type="button" data-bs-target="#carouselExampleCaptions"
                        data-bs-slide="prev">
                        <span id="cNavBtnPrev" class="carousel-control-prev-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Indietro</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#carouselExampleCaptions"
                        data-bs-slide="next">
                        <span id="cNavBtnNext" class="carousel-control-next-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Avanti</span>
                    </button>
                </div>
            </div>
        </div>
    </section>

    <!-- Become a Member Section -->
    <div class="container-fluid">
        <div class="container-xl mx-auto">
            <div id="memberSection" class="col-12">
                <p class="display-5 text-center pt-3"></p>
            </div>
        </div>
        <div id="formContainer" class="container-xl mx-auto pb-4">
            
        </div>
    </div>

    <!-- Footer Section -->
    <footer class="container-fluid justify-content-center">
        <div id="footerMainNav" class="container-xl mx-auto row text-center align-items-center">
            <div id="footerNav" class="col-md-4 col-12 py-3 align-items-center justify-content-center">
                <h5 id="footerNavHeading" class="text-center">Navigation</h5>
                <ul class="footerNavItems text-center p-0">
                    <li><a href="index.html">Home</a></li>
                    <li><a href="login.php">Login</a></li>
                    <li><a href="admin\index.php">Login Admin</a></li>
                    <li><a href="pages\about.html">Chi siamo</a></li>
                    <li><a href="manifestazioni.php">Eventi</a></li>
                </ul>
            </div>

            <div id="footerLogo" class="col-md-8 col-12 align-items-center pb-0">
                <h2 id="footerLogoText">Club auto d'epoca - ASI</h2>
                <p id="address">Italia</p>
                <p id="contact"><a href="tel:123-456-7891"> 123-456-7891 </a>&nbsp;&nbsp;|&nbsp;&nbsp; <a
                        href="mailto:info@asi.it"> info@asi.it </a></p>
                <div id="socials" class="col-12 justify-content-center">
                    <a href=""><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor"
                            class="bi bi-facebook" viewBox="0 0 16 16">
                            <path
                                d="M16 8.049c0-4.446-3.582-8.05-8-8.05C3.58 0-.002 3.603-.002 8.05c0 4.017 2.926 7.347 6.75 7.951v-5.625h-2.03V8.05H6.75V6.275c0-2.017 1.195-3.131 3.022-3.131.876 0 1.791.157 1.791.157v1.98h-1.009c-.993 0-1.303.621-1.303 1.258v1.51h2.218l-.354 2.326H9.25V16c3.824-.604 6.75-3.934 6.75-7.951z" />
                        </svg></a>
                    <a href=""><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor"
                            class="bi bi-instagram" viewBox="0 0 16 16">
                            <path
                                d="M8 0C5.829 0 5.556.01 4.703.048 3.85.088 3.269.222 2.76.42a3.917 3.917 0 0 0-1.417.923A3.927 3.927 0 0 0 .42 2.76C.222 3.268.087 3.85.048 4.7.01 5.555 0 5.827 0 8.001c0 2.172.01 2.444.048 3.297.04.852.174 1.433.372 1.942.205.526.478.972.923 1.417.444.445.89.719 1.416.923.51.198 1.09.333 1.942.372C5.555 15.99 5.827 16 8 16s2.444-.01 3.298-.048c.851-.04 1.434-.174 1.943-.372a3.916 3.916 0 0 0 1.416-.923c.445-.445.718-.891.923-1.417.197-.509.332-1.09.372-1.942C15.99 10.445 16 10.173 16 8s-.01-2.445-.048-3.299c-.04-.851-.175-1.433-.372-1.941a3.926 3.926 0 0 0-.923-1.417A3.911 3.911 0 0 0 13.24.42c-.51-.198-1.092-.333-1.943-.372C10.443.01 10.172 0 7.998 0h.003zm-.717 1.442h.718c2.136 0 2.389.007 3.232.046.78.035 1.204.166 1.486.275.373.145.64.319.92.599.28.28.453.546.598.92.11.281.24.705.275 1.485.039.843.047 1.096.047 3.231s-.008 2.389-.047 3.232c-.035.78-.166 1.203-.275 1.485a2.47 2.47 0 0 1-.599.919c-.28.28-.546.453-.92.598-.28.11-.704.24-1.485.276-.843.038-1.096.047-3.232.047s-2.39-.009-3.233-.047c-.78-.036-1.203-.166-1.485-.276a2.478 2.478 0 0 1-.92-.598 2.48 2.48 0 0 1-.6-.92c-.109-.281-.24-.705-.275-1.485-.038-.843-.046-1.096-.046-3.233 0-2.136.008-2.388.046-3.231.036-.78.166-1.204.276-1.486.145-.373.319-.64.599-.92.28-.28.546-.453.92-.598.282-.11.705-.24 1.485-.276.738-.034 1.024-.044 2.515-.045v.002zm4.988 1.328a.96.96 0 1 0 0 1.92.96.96 0 0 0 0-1.92zm-4.27 1.122a4.109 4.109 0 1 0 0 8.217 4.109 4.109 0 0 0 0-8.217zm0 1.441a2.667 2.667 0 1 1 0 5.334 2.667 2.667 0 0 1 0-5.334z" />
                        </svg></a>
                    <a href=""><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor"
                            class="bi bi-twitter" viewBox="0 0 16 16">
                            <path
                                d="M5.026 15c6.038 0 9.341-5.003 9.341-9.334 0-.14 0-.282-.006-.422A6.685 6.685 0 0 0 16 3.542a6.658 6.658 0 0 1-1.889.518 3.301 3.301 0 0 0 1.447-1.817 6.533 6.533 0 0 1-2.087.793A3.286 3.286 0 0 0 7.875 6.03a9.325 9.325 0 0 1-6.767-3.429 3.289 3.289 0 0 0 1.018 4.382A3.323 3.323 0 0 1 .64 6.575v.045a3.288 3.288 0 0 0 2.632 3.218 3.203 3.203 0 0 1-.865.115 3.23 3.23 0 0 1-.614-.057 3.283 3.283 0 0 0 3.067 2.277A6.588 6.588 0 0 1 .78 13.58a6.32 6.32 0 0 1-.78-.045A9.344 9.344 0 0 0 5.026 15z" />
                        </svg></a>
                    <a href=""><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor"
                            class="bi bi-youtube" viewBox="0 0 16 16">
                            <path
                                d="M8.051 1.999h.089c.822.003 4.987.033 6.11.335a2.01 2.01 0 0 1 1.415 1.42c.101.38.172.883.22 1.402l.01.104.022.26.008.104c.065.914.073 1.77.074 1.957v.075c-.001.194-.01 1.108-.082 2.06l-.008.105-.009.104c-.05.572-.124 1.14-.235 1.558a2.007 2.007 0 0 1-1.415 1.42c-1.16.312-5.569.334-6.18.335h-.142c-.309 0-1.587-.006-2.927-.052l-.17-.006-.087-.004-.171-.007-.171-.007c-1.11-.049-2.167-.128-2.654-.26a2.007 2.007 0 0 1-1.415-1.419c-.111-.417-.185-.986-.235-1.558L.09 9.82l-.008-.104A31.4 31.4 0 0 1 0 7.68v-.123c.002-.215.01-.958.064-1.778l.007-.103.003-.052.008-.104.022-.26.01-.104c.048-.519.119-1.023.22-1.402a2.007 2.007 0 0 1 1.415-1.42c.487-.13 1.544-.21 2.654-.26l.17-.007.172-.006.086-.003.171-.007A99.788 99.788 0 0 1 7.858 2h.193zM6.4 5.209v4.818l4.157-2.408L6.4 5.209z" />
                        </svg></a>
                </div>
            </div>
        </div>

        <div id="copyright" class="container-xl mx-auto row text-center">
            <div class="col-12 my-auto py-3">
                <p id="copyrightMsg"> &copy; Copyright 2025 Gruppo Castello. All Rights Reserved.</p>
                <p id="endingNote">Fatto da Alessandro D.Z, Alessandro M., Tommaso P. <a href="https://github.com/aledezzy/asi-castello/"
                        target="_blank">Gruppo Castello</a></p>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"
    integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous">
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.1/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-u1OknCvxWvY5kfmNBILK2hRnQC3Pr17a+RTT6rIHI7NnikvbZlHgTPOOmMi466C8" crossorigin="anonymous">
    </script>
    <script type="text/javascript" src="js/script.js"></script>
</body>
</html>
