# Sync Extension (4viewture/sync)

Diese Erweiterung ermöglicht die Synchronisation von News zwischen verschiedenen TYPO3-Instanzen oder externen Quellen.
Sie kann sowohl als Empfänger (Import) als auch als Bereitsteller von Daten fungieren.

## Funktionen

*   **Import von News**: Unterstützt verschiedene Formate (RSS, JSON, WXR, etc.) über spezialisierte Provider.
*   **Provider-System**: Modulares System zur Anbindung verschiedener Datenquellen.
*   **Backend-Modul**: Übersichtliche Verwaltung und manuelles Anstoßen von Synchronisationen.
*   **Automatisierung**: Integration in den TYPO3-Scheduler für regelmäßige Updates.
*   **Provider-Übersicht**: Detaillierte Liste aller registrierten Sync-Provider mit Beschreibung und Dokumentationslinks.

## Voraussetzungen

*   TYPO3 v12+ (aufgrund der verwendeten ModuleTemplate API)
*   Extension `news` (GeorgRinger/news)
*   PHP-Paket `phpstan/phpdoc-parser` (für die automatische Extraktion von Provider-Beschreibungen)

## Einrichtung

1.  **Installation**: Die Extension wie gewohnt installieren (per Composer oder Extension Manager).
2.  **Sysfolder anlegen**: Erstellen Sie einen Sysfolder für die Sync-Konfigurationen und die importierten Daten.
3.  **Sync-Konfiguration anlegen**: Erstellen Sie im Sysfolder neue Datensätze vom Typ "Synchronisations-Konfiguration".
    *   Wählen Sie den passenden **Provider** aus (oder nutzen Sie "Auto", falls unterstützt).
    *   Geben Sie die **Quell-URL** an.
    *   Konfigurieren Sie Zielordner und Kategorien.
4.  **Scheduler Task**: Legen Sie einen Task vom Typ "Execute console commands" mit dem Befehl `sync:all` an, um die Synchronisation zu automatisieren.

## Backend-Modul

Das Modul findet sich im Hauptmenü unter "Web" -> "Sync". Es bietet zwei Hauptansichten:

1.  **Synchronisation**: Liste aller Konfigurationen mit Status (letzter Sync, Log) und Buttons zum manuellen Starten oder Ansehen der Quelle.
2.  **Provider Übersicht**: Eine Liste aller im System registrierten Sync-Provider. Hier werden Name, Beschreibung (automatisch aus dem PHP-Doc-Block extrahiert), die implementierende Klasse und ggf. Links zur Dokumentation angezeigt.

## Eigene Provider entwickeln

Um einen eigenen Provider zu erstellen, muss eine Klasse das `ImportProviderInterface` implementieren (am besten durch Ableitung von `AbstractImportService`).

### Beispiel:

```php
/**
 * Dieser Provider importiert News aus einer speziellen JSON-Schnittstelle.
 * Weitere Details zur Konfiguration finden sich in der Dokumentation.
 *
 * @link https://example.com/docs/my-provider
 */
class MyCustomProvider extends AbstractImportService
{
    public const PRIORITY = 50;

    public function canHandle(SyncConfiguration $syncConfiguration): bool
    {
        return str_ends_with($syncConfiguration->getUri(), '.json');
    }

    public function handle(SyncConfiguration $syncConfiguration): void
    {
        // Implementierung der Logik
    }
}
```

Die Beschreibung in der Provider-Übersicht wird automatisch aus dem Klassen-Doc-Block extrahiert (bis zum ersten Tag). Der `@link` Tag wird als Dokumentations-URL verwendet (sofern in der Klasse überschrieben oder im Interface implementiert).

## Lizenz

GPL v2 or later
