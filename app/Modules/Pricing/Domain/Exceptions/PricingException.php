<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Domain\Exceptions;

use RuntimeException;

/**
 * Pedar e hame ye khata haye domain e Pricing.
 *
 * Laye ye Livewire mitune hamin yeki ro catch kone va payam esh ro mostaghim
 * be karbar neshun bede — payam ha amdan Farsi-and (be karbar neshun dade
 * mishan)، bar khalaf e comment ha ke Finglish-and.
 */
abstract class PricingException extends RuntimeException {}
