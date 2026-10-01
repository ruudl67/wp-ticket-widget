# Wijziging aanvragen (Ruud Licht) — WordPress-plugin

Zet een kort formulier "Wijziging aanvragen aan uw website" in het WordPress-dashboard van elke
site waar deze plugin op draait. Een verzoek komt direct binnen in de Servicedesk
(`taken.ruudlicht.nl`) — geen account, e-mail of aparte link nodig voor de klant.

## Installeren op een site

1. Download de laatste versie: **Code → Download ZIP** op deze pagina (of een losse release onder
   [Releases](../../releases)).
2. In WordPress: **Plugins → Nieuwe plugin toevoegen → Plugin uploaden**, kies de zip, **Installeren**
   en **Activeren**.
3. Klaar — geen instellingen nodig. De plugin herkent automatisch het domein en de naam van de site.

## Updaten

Omdat deze plugin vanaf GitHub wordt bijgewerkt, verschijnt bij een nieuwe versie gewoon het normale
"Update beschikbaar"-balkje bij **Plugins** op elke site waar hij geïnstalleerd is — bijwerken gaat
dan met één klik, zoals bij elke andere plugin.

## Een nieuwe versie uitbrengen

1. Verhoog het versienummer in de `Version:`-regel bovenin `ruud-ticket-widget.php`.
2. Commit en push naar de `main`-branch (of maak een GitHub Release aan voor een stabielere aanpak).
3. Binnen ~12 uur (of direct als iemand handmatig op "Nu bijwerken controleren" klikt) zien alle sites
   met deze plugin de update.
