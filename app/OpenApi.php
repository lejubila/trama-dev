<?php

declare(strict_types=1);

namespace App;

/**
 * Top-level OpenAPI metadata for Trama Network. Augmented per-controller as
 * we annotate individual endpoints; for FASE 7 we ship a minimal but valid
 * spec covering the resource shape and the auth scheme.
 *
 * @OA\Info(
 *   title="Trama Network REST API",
 *   version="1.0.0",
 *   description="API per integrazioni esterne (provisioning, monitoring, automazioni). Tutti gli endpoint richiedono Bearer token (Sanctum) e l'header X-Tenant-Id."
 * )
 *
 * @OA\Server(url="/api", description="Trama Network API")
 *
 * @OA\SecurityScheme(
 *   securityScheme="sanctum",
 *   type="http",
 *   scheme="bearer",
 *   bearerFormat="Sanctum"
 * )
 *
 * @OA\Parameter(
 *   parameter="TenantHeader",
 *   name="X-Tenant-Id",
 *   in="header",
 *   required=true,
 *   description="ID del tenant (cliente) sul quale opera la richiesta",
 *
 *   @OA\Schema(type="integer", minimum=1)
 * )
 *
 * @OA\Tag(name="Sites", description="Sedi del cliente")
 * @OA\Tag(name="Rooms", description="Locali tecnici dentro una sede")
 * @OA\Tag(name="Racks", description="Rack di un locale")
 * @OA\Tag(name="Equipment", description="Dispositivi di rete")
 * @OA\Tag(name="Interfaces", description="Interfacce dei dispositivi")
 * @OA\Tag(name="Connections", description="Connessioni fisiche tra interfacce")
 * @OA\Tag(name="Topology", description="Vista a grafo dell'infrastruttura")
 * @OA\Tag(name="Tokens", description="Gestione personal access token")
 */
final class OpenApi
{
    // Holder class for OpenAPI annotations only.
}
