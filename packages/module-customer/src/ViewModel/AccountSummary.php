<?php
/**
 * This file is part of the MageObsidian - Customer project.
 *
 * @license MIT License - See the LICENSE file in the root directory for details.
 * © 2026 Jeanmarcos Juarez
 */

declare(strict_types=1);

namespace MageObsidian\Customer\ViewModel;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Helper\Session\CurrentCustomer;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Who the account rail is greeting.
 *
 * Reading the customer server-side is only safe because every account content
 * block is declared cacheable="false"; a cacheable page would have to take this
 * from the client-side `customer` section instead, the way the header island does.
 */
class AccountSummary implements ArgumentInterface
{
    public function __construct(
        private readonly CurrentCustomer $currentCustomer,
        private readonly TimezoneInterface $timezone
    ) {
    }

    public function getName(): string
    {
        $customer = $this->getCustomer();
        if ($customer === null) {
            return '';
        }

        return trim($customer->getFirstname() . ' ' . $customer->getLastname());
    }

    public function getFirstName(): string
    {
        return (string)($this->getCustomer()?->getFirstname() ?? '');
    }

    public function getEmail(): string
    {
        return (string)($this->getCustomer()?->getEmail() ?? '');
    }

    /**
     * Up to two letters for the monogram tile. Falls back to the email so the
     * tile is never an empty box for a customer with no name on file.
     */
    public function getInitials(): string
    {
        $customer = $this->getCustomer();
        if ($customer === null) {
            return '';
        }

        $letters = array_filter([
            mb_substr((string)$customer->getFirstname(), 0, 1),
            mb_substr((string)$customer->getLastname(), 0, 1),
        ], static fn (string $letter): bool => $letter !== '');

        if ($letters === []) {
            $letters = [mb_substr((string)$customer->getEmail(), 0, 1)];
        }

        return mb_strtoupper(implode('', $letters));
    }

    /**
     * Raw creation date, for the template to run through the `date_format` filter
     * — formatting is a presentation choice and belongs in Twig, not here.
     */
    public function getMemberSince(): string
    {
        return (string)($this->getCustomer()?->getCreatedAt() ?? '');
    }

    /**
     * The sign-up year alone, for the dashboard tile. Not derivable in Twig: the
     * `date_format` filter only takes Intl styles (short/medium/long), and a bare
     * substr of the UTC timestamp puts a New Year's Eve sign-up in the wrong year
     * for every store west of Greenwich.
     */
    public function getMemberSinceYear(): string
    {
        $createdAt = $this->getMemberSince();

        return $createdAt === '' ? '' : $this->timezone->date($createdAt)->format('Y');
    }

    public function getAddressCount(): int
    {
        return count($this->getCustomer()?->getAddresses() ?? []);
    }

    private function getCustomer(): ?CustomerInterface
    {
        try {
            return $this->currentCustomer->getCustomer();
        } catch (NoSuchEntityException) {
            return null;
        }
    }
}
