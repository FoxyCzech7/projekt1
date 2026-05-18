-- ============================================================
-- Vojenská Technika — seed příspěvků
-- Před spuštěním uprav user_id na ID tvého admin účtu
-- ============================================================

SET @admin_id = 1;

INSERT INTO posts (title, content, user_id, created_at, likes_count, is_premium, image) VALUES

('F-35 Lightning II: Neviditelný vládce oblohy',
'F-35 Lightning II je jednomotorový stealth víceúčelový bojový letoun páté generace vyvinutý společností Lockheed Martin. Program Joint Strike Fighter (JSF), v jehož rámci F-35 vznikl, představuje největší zbrojní zakázku v historii – celkové náklady přesahují 1,7 bilionu dolarů.

Letoun existuje ve třech variantách: F-35A pro konvenční vzlet a přistání (CTOL), F-35B s krátkým vzletem a svislým přistáním (STOVL) a F-35C určený pro palubní provoz na letadlových lodích. Sdílí přibližně 20–25 % konstrukčních prvků, přičemž každá varianta je optimalizována pro odlišné bojové scénáře.

Stealthová technologie F-35 spočívá v kombinaci několika přístupů. Tvar draku letounu je navržen tak, aby odrážel radarové vlny mimo přijímač nepřítele. Speciální absorpční povlaky RAM (Radar-Absorbent Material) pohlcují elektromagnetické záření. Výfukové plyny jsou chlazeny ještě před výstupem z trysky, čímž se snižuje infračervený podpis letounu. Výsledkem je efektivní radarový průřez (RCS) přibližně 0,001 m² – srovnatelný s hmyzem na radarovém displeji.

Avionika představuje druhou klíčovou přednost F-35. Systém Distributed Aperture System (DAS) tvoří šest infračervených kamer rozmístěných po trupu, které pilotovi poskytují bezešvý 360° pohled skrze trup letadla. Přilba HMDS (Helmet Mounted Display System) promítá veškeré taktické informace přímo do zorného pole pilota – pilot tedy nemusí pohybovat hlavou k přístrojovému panelu.

Výzbroj zahrnuje interní zbraňové prostory pro zachování stealthu – standardně dvě řízené střely AIM-120 AMRAAM a dvě bomby JDAM. Pro mise bez požadavku na stealth lze použít šest externích závěsníků s celkovou nosností přes 6 000 kg. Kanón GAU-22/A ráže 25 mm je integrován v trupu (F-35A) nebo nesenou gondolou (F-35B/C).

Ke dni 2024 provozuje F-35 patnáct zemí včetně USA, Velké Británie, Izraele, Japonska, Jižní Koreje a od roku 2023 i Finska a Švýcarska. Česká republika zahájila jednání o možném pořízení F-35 jako náhrady za zastarávající gripen.',
@admin_id, DATE_SUB(NOW(), INTERVAL 12 DAY), 0, 0, NULL),

('Leopard 2A8: Evoluční vrchol německého tankového inženýrství',
'Leopard 2 vstoupil do služby Bundeswehru v roce 1979 a od té doby prošel sedmi hlavními vývojovými skoky. Varianta 2A8, jejíž vývoj byl urychlen zkušenostmi z války na Ukrajině, kombinuje osvědčený základ s technologiemi 21. století a představuje zatím nejpokročilejší člen rodiny.

Pancéřová ochrana 2A8 přešla od modularní kompozitní ochrany k systému aktivní obrany Trophy APS (Active Protection System) izraelské výroby. Trophy detekuje přilétající hrozby – protitankové rakety i RPG – pomocí čtyř radar a neutralizuje je výstřelem speciálních protiopatření ještě před dopadem. Systém byl poprvé bojově ověřen na izraelských Merkavy v Gaze a prokázal 100% úspěšnost proti hrozbám třídy RPG-7 a Kornet.

Hlavní zbraní zůstává osvědčený 120mm hladkostrý kanón Rheinmetall L/55A1 – jeden z nejpřesnějších tankových kanónů na světě. Nová munice DM73 s penetrátorem z wolfram-niklové slitiny proniká přes 700 mm homogenní oceli na vzdálenost 2 000 metrů. Rychlost střely opouštějící hlaveň přesahuje 1 700 m/s.

Pohonná soustava MB 873 Ka-501 o výkonu 1 500 koní zajišťuje maximální rychlost 72 km/h na silnici při bojové hmotnosti 62 tun. Spotřeba paliva dosahuje 500 litrů na 100 km v terénu – logistická výzva, která pohání výzkum hybridního pohonu pro budoucí generaci.

Bojová zkušenost z Ukrajiny přinesla zásadní lekce. Leopardy 2A4 a 2A6 dodané Kyjevu sice prokázaly přesnost a spolehlivost, ale také zranitelnost vůči minám a dronům FPV. Verze 2A8 proto přináší vylepšenou ochranu spodku (belly armor), integrované systémy pro detekci a rušení dronů a rozšírenou situační bilitu prostřednictvím kamer C-UAS.',
@admin_id, DATE_SUB(NOW(), INTERVAL 9 DAY), 0, 0, NULL),

('FPV drony: Jak levný hardware mění pravidla moderní války',
'First Person View drony – původně sportovní závodní kategorie – se staly jednou z nejdestruktivnějších a nejkontroverznějších zbraní konfliktu na Ukrajině. Cena sestřelené obrněné techniky v hodnotě milionů dolarů mnohonásobně přesahuje hodnotu dronu FPV, jehož výroba stojí od 300 do 800 dolarů.

Základní FPV dron bojového nasazení sestává z komerčního závodního rámu o velikosti 5 palců (vzdálenost 250 mm mezi motory), čtyř bezkartáčových motorů 2306 nebo 2207, řídicí desky FC (Flight Controller) s gyrostabilizací, FPV kamery a video vysílače, přijímače RC signálu na 915 MHz nebo 2,4 GHz a bojové hlavice – nejčastěji upraveného granátu PG-7 z RPG nebo kumulativní nálože.

Útočný dron letí rychlostí 100–150 km/h s doletem 5–10 km při vizuálním pilotování. Pilot vidí záběr kamery přes FPV brýle s latencí pod 30 milisekund – klíčový parametr pro přesné navedení na cíl. Průběžná cena jednoho nasazení (dron + munice) se pohybuje od 600 do 1 200 dolarů.

Obranná opatření se vyvíjejí stejně rychle jako útočné techniky. Softwarové systémy DroneShield a Bulwark RF detekují charakteristické rádiové emise FPV vysílačů. Elektronické rušiče jammeru Krasukha nebo REX-1 blokují řídicí frekvence. Pasivní ochrana zahrnuje klecové koše z armovací oceli nad motorovými prostory vozidel – konstrukčně primitivní, ale překvapivě účinné.

Autonomní navádění pomocí computer vision (CV) eliminuje závislost na rádiové lince. Drony s GPU procesory jako Raspberry Pi CM4 nebo NVIDIA Jetson Nano dokáží trackovat cíl bez RC signálu po dobu posledních sekund letu. Tato technologie výrazně komplikuje jamming jako obrannou strategii.',
@admin_id, DATE_SUB(NOW(), INTERVAL 6 DAY), 0, 0, NULL),

('Hypersonické zbraně: Závod o rychlost Mach 5+',
'Hypersonické zbraně – letící rychlostí přesahující Mach 5 (6 125 km/h) – představují kvalitativní zlom ve strategickém zbrojení. Kombinace extrémní rychlosti, manévrovatelnosti za letu a nízké výšky trajektorie činí ze současných systémů protiraketové obrany (BMD) prakticky neúčinné.

Existují dvě hlavní kategorie hypersonických zbraní. Hypersonická klouzavá tělesa (HGV – Hypersonic Glide Vehicle) jsou uváděna do pohybu balistickou raketou, která je vynese do výšky přibližně 100 km. Odtud HGV plachtí a manévruje v hustší atmosféře rychlostí Mach 15–20. Ruský Avangard, čínský DF-ZF a americký LRHW (Long Range Hypersonic Weapon) patří do této kategorie.

Hypersonické manévrující střely (HCM – Hypersonic Cruise Missile) využívají scramjet pohon po celou dobu letu v atmosféře. Scramjet (supersonic combustion ramjet) spaluje palivo ve vzduchu komprimovaném aerodynamicky při nadzvukové rychlosti. Neobsahuje pohyblivé části – vnitřní geometrie motoru je navržena tak, aby průchod vzduchu sám udržoval spalovací proces. Ruský Kinžal, který byl nasazen na Ukrajině, je ve skutečnosti upravenou balistickou střelou na hypersonické rychlosti – nikoliv pravým HCM.

Detekce hypersonických hrozeb vyžaduje zcela nové senzorické systémy. Pozemní radary třídy S-400 nebo Patriot mají horizont detekce typicky 400–600 km – při rychlosti Mach 10 to znamená méně než dvě minuty reakčního času. Program HBTSS (Hypersonic and Ballistic Tracking Space Sensor) USA plánuje konstelaci satelitů na nízké orbitě (LEO) sledující hypersonické hrozby v infračerveném spektru nepřetržitě z vesmíru.',
@admin_id, DATE_SUB(NOW(), INTERVAL 3 DAY), 0, 1, NULL),

('USS Gerald R. Ford (CVN-78): Letadlová loď 21. století',
'USS Gerald R. Ford vstoupila do aktivní služby amerického námořnictva v roce 2017 jako první plavidlo nové třídy. S délkou 337 metrů, výtlakem 100 000 tun a plochou letové paluby přes 18 000 m² je největší válečnou lodí, jaká kdy byla postavena. Cena jednoho exempláře překročila 13 miliard dolarů.

Klíčovou inovací oproti předchozí třídě Nimitz je elektromagnetický katapultový systém EMALS (Electromagnetic Aircraft Launch System). Nahrazuje parní katapulty využívané od 50. let. EMALS používá lineární indukční motor napájený z kondenzátorových bateriích. Výhody jsou trojí: plynulejší akcelerace prodlužuje životnost draku letounů, systém lze kalibrovat pro různé hmotnosti letadel (od dronů po bombardéry) a spotřeba energie je o 30 % nižší než u parního systému.

Raketoplán Advanced Arresting Gear (AAG) plní opačnou funkci – zastavuje přistávající letouny pomocí elektromagneticky řízeného odporu místo hydrauliky. Přistávající F/A-18 Super Hornet v hmotnosti 20 tun zastaví ze 240 km/h na nulu za 90 metrů.

Pohon zajišťují dva jaderné reaktory A1B společnosti Bechtel – nová generace navržená speciálně pro třídu Ford. Každý reaktor generuje výkon přibližně 300 MW tepelné energie. Celkový elektrický výkon 104 MW (oproti 64 MW u Nimitz) je klíčový pro zásobování energeticky náročných systémů jako EMALS, AAG, pokročilý radar DBR (Dual Band Radar) nebo budoucí laserové zbraňové systémy.

Letecká skupina standardně zahrnuje 75 letadel různých typů: F/A-18E/F Super Hornet, EA-18G Growler pro elektronický boj, E-2D Advanced Hawkeye pro vzdušné řízení a varování, MH-60R/S Seahawk helikoptéry a od roku 2025 i bezpilotní průzkumné letouny MQ-25 Stingray. MQ-25 jako první palubní dron schopný vzdušného tankování výrazně prodlouží dosah útočných letounů.',
@admin_id, DATE_SUB(NOW(), INTERVAL 1 DAY), 0, 1, NULL);
