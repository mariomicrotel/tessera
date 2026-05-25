<?php

namespace App\Services\Consultant\Export;

use App\Services\Consultant\Export\Contracts\DataSource;
use App\Services\Consultant\Export\Contracts\ExportFormat;
use App\Services\Consultant\Export\DataSources\CespitiDataSource;
use App\Services\Consultant\Export\DataSources\CompensiTerziDataSource;
use App\Services\Consultant\Export\DataSources\FattureAttiveDataSource;
use App\Services\Consultant\Export\DataSources\FatturePassiveDataSource;
use App\Services\Consultant\Export\DataSources\FornitoriDataSource;
use App\Services\Consultant\Export\DataSources\IncassiDataSource;
use App\Services\Consultant\Export\DataSources\ModelliF24DataSource;
use App\Services\Consultant\Export\DataSources\MovimentiBancariDataSource;
use App\Services\Consultant\Export\DataSources\RimborsiSpeseDataSource;
use App\Services\Consultant\Export\DataSources\SpeseDataSource;
use App\Services\Consultant\Export\Formats\CsvGenericFormat;
use InvalidArgumentException;

/**
 * Registro centralizzato di tutti i formati di export e di tutte le DataSource.
 *
 * È volutamente statico/registrato in codice (no DB) perché:
 *  - i formati sono codice, non dati
 *  - una migration "aggiunge formato" non ha senso: serve sempre nuova classe
 *  - rende esplicito quali formati sono supportati a un dato deploy
 *
 * Per aggiungere un formato/datasource: aggiungi la riga in formats() o dataSources().
 */
class ExportFormatRegistry
{
    /**
     * @return array<string, ExportFormat>  keyed by ExportFormat::key()
     */
    public function formats(): array
    {
        $formats = [
            new CsvGenericFormat(),
            // new AgenziaEntrateXmlFormat(),  // aggiunto in Day 3
        ];

        $map = [];
        foreach ($formats as $f) {
            $map[$f->key()] = $f;
        }
        return $map;
    }

    /**
     * @return array<string, DataSource>  keyed by DataSource::key()
     */
    public function dataSources(): array
    {
        $sources = [
            new FattureAttiveDataSource(),
            new FatturePassiveDataSource(),
            new IncassiDataSource(),
            new SpeseDataSource(),
            new MovimentiBancariDataSource(),
            new CompensiTerziDataSource(),
            new ModelliF24DataSource(),
            new FornitoriDataSource(),
            new CespitiDataSource(),
            new RimborsiSpeseDataSource(),
        ];

        $map = [];
        foreach ($sources as $s) {
            $map[$s->key()] = $s;
        }
        return $map;
    }

    /**
     * Restituisce un formato per key, o lancia se non esiste.
     */
    public function format(string $key): ExportFormat
    {
        $formats = $this->formats();
        if (! isset($formats[$key])) {
            throw new InvalidArgumentException("Formato export sconosciuto: {$key}");
        }
        return $formats[$key];
    }

    /**
     * Restituisce un DataSource per key, o lancia se non esiste.
     */
    public function dataSource(string $key): DataSource
    {
        $sources = $this->dataSources();
        if (! isset($sources[$key])) {
            throw new InvalidArgumentException("DataSource sconosciuto: {$key}");
        }
        return $sources[$key];
    }

    /**
     * Risolve un array di chiavi DataSource filtrato dai supportedDataSources del formato.
     *
     * Esempio: formato csv_generic supporta tutto → restituisce tutti.
     *          formato agenzia_entrate_xml supporta solo fatture → restituisce solo quelle.
     *
     * @param  ExportFormat  $format
     * @param  array<string> $requestedKeys  Chiavi DataSource richieste dal consulente
     * @return array<DataSource>             Istanze concrete supportate
     */
    public function resolveDataSourcesFor(ExportFormat $format, array $requestedKeys): array
    {
        $supported = $format->supportedDataSources();
        $sources = $this->dataSources();
        $result = [];

        foreach ($requestedKeys as $key) {
            if (! isset($sources[$key])) {
                continue; // chiave sconosciuta → ignora (sanitize)
            }
            if ($supported !== null && ! in_array($key, $supported, true)) {
                continue; // non supportata da questo formato → skip
            }
            $result[] = $sources[$key];
        }

        return $result;
    }

    /**
     * Metadati di tutti i formati per il form UI (Inertia props).
     *
     * @return array<int, array{key:string, label:string, description:string, supported_data_sources:array<string>|null, extension:string}>
     */
    public function formatsForUi(): array
    {
        $list = [];
        foreach ($this->formats() as $f) {
            $list[] = [
                'key'                    => $f->key(),
                'label'                  => $f->label(),
                'description'            => $f->description(),
                'supported_data_sources' => $f->supportedDataSources(),
                'extension'              => $f->extension(),
            ];
        }
        return $list;
    }

    /**
     * Metadati di tutte le DataSource per il form UI.
     *
     * @return array<int, array{key:string, label:string, description:string, file_name:string}>
     */
    public function dataSourcesForUi(): array
    {
        $list = [];
        foreach ($this->dataSources() as $s) {
            $list[] = [
                'key'         => $s->key(),
                'label'       => $s->label(),
                'description' => $s->description(),
                'file_name'   => $s->fileName(),
            ];
        }
        return $list;
    }
}
