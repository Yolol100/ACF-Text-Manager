# Repository 12/37 — ACF Page Text Manager: importcontrole

Auditdatum: **5 november 2026**. Basis: `main` commit `57d8bb00b265a149aec80eeaf936cff53f812af1`.
Concept: [PR #10](https://github.com/Yolol100/ACF-Text-Manager/pull/10), branch `quality/import-preview-conflict-20261105`.

**Geen productie, echte klantgegevens, WordPress-mediametadata, Yoast/Rank Math-data of live imports gewijzigd.** Alle schrijfproeven gebruiken een synthetische ACF-pagina binnen de tijdelijke GitHub Actions WordPress/MySQL-runtime.

## Bevestigde bug — ACF-PTM-001 (risico op verlies van recent bewerkte inhoud)

`Import_Plan_Builder::build()` legt de waarde `current_value` van een veld vast tijdens de importvoorvertoning. `Import_Processor::process()` nam die oude momentopname wel mee in het plan, maar controleerde de actuele veldwaarde **niet opnieuw vóór het wegschrijven**. Als een redacteur tussen het bekijken en uitvoeren van de import een veld aanpast, wordt die nieuwere waarde stil overschreven.

- **Voor-bewijs:** [rode CI #41972750089](https://github.com/Yolol100/ACF-Text-Manager/actions/runs/41972750089) op **WP 6.5/PHP 8.0** en **WP 7.1/PHP 8.3**, met echte `acf_add_local_field_group`, `update_field` en `Import_Processor`. Log: `Stale ACF import preview conflict in stale preview after editor change` met `field=import-target` in plaats van de recentere `editor-update`.
- **Reparatie:** in de bestaande `Import_Processor` vóór elke gewone of speciale veldwijziging de actuele waarde opnieuw lezen, met `Field_Value_Service::values_match_for_conflict_check()` vergelijken met de geplande oorspronkelijke waarde en bij afwijkingen niet overschrijven. Als de doelwaarde al aanwezig is, volgt geen extra schrijfactie. Ontbrekende snapshotvelden falen veilig en geven een rijmelding.
- **Testcases:** (1) verouderd importplan bewaart recentere handmatige bewerking, (2) ongewijzigd plan werkt nog steeds, (3) al toegepaste doelwaarde blijft zonder wijziging goed. Test wordt uitgevoerd als `tests/wp-cli-import-preview-conflict.php` in de bestaande CI.
- **Eindbewijs:** controleer groen op de **definitieve PR-head**, ook na dit document. Oudere groene codejobs zijn niet voldoende.

## Vergeleken publieke implementaties / waarom

1. [WordPress Core `update_metadata(..., $prev_value)`](https://developer.wordpress.org/reference/functions/update_metadata/): kan alleen bij passende eerdere waarde updaten; hetzelfde optimistic-concurrencyprincipe toegepast op de ACF-importflow. Een ruwe `update_post_meta` was hier geen drop-invervanger vanwege ACF-veldreferenties en speciale contenttypes.
2. [ACF `get_field()`](https://www.advancedcustomfields.com/resources/get_field/) en [`update_field()`](https://www.advancedcustomfields.com/resources/update_field/): gebruik de veldkey en ACF API om bestaande referenties niet te breken. De bestaande API blijft behouden.
3. [10up WP Scaffold](https://github.com/10up/wp-scaffold): regressietest in bestaande repository-native CI, geen tweede parallel testframework.

## Nog open: bewust niet gewijzigd

- **TOCTOU**: het lezen en bijwerken is niet volledig database-atomair. Twee gelijktijdige writes *tussen check en update* kunnen nog racen. Locking of transacties vergen aparte staging- en compatibiliteitstests.
- De test dekt gewone ACF-tekstvelden. **Speciale velden** (Yoast, Rank Math, bijlagen, titels), media hernoemen, ZIP/XLSX, ACF-repeater/clone, WPML en multisite hebben nog niet alle eigen praktijkregressies.
- Bestanden `*.mo`, gegenereerde JS, thema-integraties en migratiepaden zijn behouden; geen verwijdering van schijnbaar dubbele code zonder bewijs van bereikbaarheid.
- Geen echte import-/export-rollback op staging getest; de planpreview is niet gelijk aan een volledige transactie of herstelpunt.
- Runtime CI bewijst alleen syntax, pluginactivatie en synthetische testgedragingen. Voor vrijgave: staging-backup en voor/na snapshots, fout- en herstelpad, gebruikersrechten, performance, review, pakketversie en ZIP-pariteit.

**Rollback:** sluit PR #10. `main` blijft ongewijzigd tot beoordeling.
