<?php
/* For licensing terms, see /license.txt */

namespace Chamilo\CoreBundle\Behat;

use Behat\Gherkin\Node\TableNode;
use Chamilo\UserBundle\Entity\User;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Class CoreContext
 * @package Chamilo\CoreBundle\Behat
 */
class CoreContext extends DefaultContext
{
    /**
     * @Given /^there are following orders:$/
     * @Given /^the following orders exist:$/
     * @Given /^there are orders:$/
     * @Given /^the following orders were placed:$/
     */
    public function thereAreOrders(TableNode $table)
    {
        $manager = $this->getEntityManager();
        $finite  = $this->getService('sm.factory');
        $orderRepository   = $this->getRepository('order');
        $shipmentProcessor = $this->getService('sylius.processor.shipment_processor');

        /** @var $paymentMethod PaymentMethodInterface */
        $paymentMethod = $this->getRepository('payment_method')->createNew();
        $paymentMethod->setName('Stripe');
        $paymentMethod->setGateway('stripe');
        $manager->persist($paymentMethod);

        $currentOrderNumber = 1;
        foreach ($table->getHash() as $data) {
            $address = $this->createAddress($data['address']);

            /* @var $order OrderInterface */
            $order = $orderRepository->createNew();
            $order->setShippingAddress($address);
            $order->setBillingAddress($address);

            $order->setUser($this->thereIsUser($data['user'], 'sylius'));

            if (isset($data['shipment']) && '' !== trim($data['shipment'])) {
                $order->addShipment($this->createShipment($data['shipment']));
            }

            $order->setNumber(str_pad($currentOrderNumber, 9, 0, STR_PAD_LEFT));

            $finite->get($order, OrderTransitions::GRAPH)->apply(OrderTransitions::SYLIUS_CREATE);

            $this->createPayment($order, $paymentMethod);

            $order->setCurrency('EUR');
            $order->complete();

            $shipmentProcessor->updateShipmentStates($order->getShipments(), ShipmentTransitions::SYLIUS_PREPARE);

            $manager->persist($order);

            $this->orders[$order->getNumber()] = $order;

            ++$currentOrderNumber;
        }

        $manager->flush();
    }

    /**
     * @Given /^order #(\d+) has following items:$/
     */
    public function orderHasFollowingItems($number, TableNode $items)
    {
        $manager = $this->getEntityManager();
        $orderItemRepository = $this->getRepository('order_item');

        $order = $this->orders[$number];

        foreach ($items->getHash() as $data) {
            $product = $this->findOneByName('product', trim($data['product']));

            /* @var $item OrderItemInterface */
            $item = $orderItemRepository->createNew();
            $item->setVariant($product->getMasterVariant());
            $item->setUnitPrice($product->getMasterVariant()->getPrice());
            $item->setQuantity($data['quantity']);

            $order->addItem($item);
        }

        $order->calculateTotal();
        $order->complete();

        $this->getService('sylius.order_processing.payment_processor')->createPayment($order);
        $this->getService('event_dispatcher')->dispatch(SyliusCartEvents::CART_CHANGE, new GenericEvent($order));

        $manager->persist($order);
        $manager->flush();
    }


    /**
     * @Given /^there are groups:$/
     * @Given /^there are following groups:$/
     * @Given /^the following groups exist:$/
     */
    public function thereAreGroups(TableNode $table)
    {
        $manager = $this->getEntityManager();
        $repository = $this->getRepository('group');

        foreach ($table->getHash() as $data) {
            $group = $repository->createNew();
            $group->setName(trim($data['name']));

            $roles = explode(',', $data['roles']);
            $roles = array_map('trim', $roles);

            $group->setRoles($roles);

            $manager->persist($group);
        }

        $manager->flush();
    }

    /**
     * @Given /^product "([^""]*)" has the following volume based pricing:$/
     */
    public function productHasTheFollowingVolumeBasedPricing($productName, TableNode $table)
    {
        /* @var $product ProductInterface */
        $product = $this->findOneByName('product', $productName);
        $masterVariant = $product->getMasterVariant();

        /* @var $masterVariant ProductVariantInterface */
        $masterVariant->setPricingCalculator(PriceCalculators::VOLUME_BASED);
        $configuration = array();

        foreach ($table->getHash() as $data) {
            if (false !== strpos($data['range'], '+')) {
                $min = null;
                $max = (int) trim(str_replace('+', '', $data['range']));
            } else {
                list($min, $max) = array_map(function ($value) { return (int) trim($value); }, explode('-', $data['range']));
            }

            $configuration[] = array(
                'min'   => $min,
                'max'   => $max,
                'price' => (int) $data['price'] * 100
            );
        }

        $masterVariant->setPricingConfiguration($configuration);

        $manager = $this->getEntityManager();
        $manager->persist($product);
        $manager->flush();
    }

    /**
     * @Given /^product "([^""]*)" has the following group based pricing:$/
     */
    public function productHasTheFollowingGroupBasedPricing($productName, TableNode $table)
    {
        $product = $this->findOneByName('product', $productName);
        $masterVariant = $product->getMasterVariant();

        /* @var $masterVariant ProductVariantInterface */
        $masterVariant->setPricingCalculator(PriceCalculators::GROUP_BASED);
        $configuration = array();

        foreach ($table->getHash() as $data) {
            $group = $this->findOneByName('group', trim($data['group']));
            $configuration[$group->getId()] = (float) $data['price'] * 100;
        }

        $masterVariant->setPricingConfiguration($configuration);

        $manager = $this->getEntityManager();
        $manager->persist($product);
        $manager->flush();
    }

    /**
     * @Given /^there are following tax rates:$/
     * @Given /^the following tax rates exist:$/
     */
    public function thereAreTaxRates(TableNode $table)
    {
        foreach ($table->getHash() as $data) {
            $this->thereIsTaxRate($data['amount'], $data['name'], $data['category'], $data['zone'], isset($data['included in price?']) ? $data['included in price?'] : false, false);
        }

        $this->getEntityManager()->flush();
    }

    /**
     * @Given /^there is (\d+)% tax "([^""]*)" for category "([^""]*)" within zone "([^""]*)"$/
     * @Given /^I created (\d+)% tax "([^""]*)" for category "([^""]*)" within zone "([^""]*)"$/
     */
    public function thereIsTaxRate($amount, $name, $category, $zone, $includedInPrice = false, $flush = true)
    {
        /* @var $rate TaxRateInterface */
        $rate = $this->getRepository('tax_rate')->createNew();
        $rate->setName($name);
        $rate->setAmount($amount / 100);
        $rate->setIncludedInPrice($includedInPrice);
        $rate->setCategory($this->findOneByName('tax_category', $category));
        $rate->setZone($this->findOneByName('zone', $zone));
        $rate->setCalculator('default');

        $manager = $this->getEntityManager();
        $manager->persist($rate);
        if ($flush) {
            $manager->flush();
        }

        return $rate;
    }

    /**
     * @Given /^the following shipping methods are configured:$/
     * @Given /^the following shipping methods exist:$/
     * @Given /^there are shipping methods:$/
     */
    public function thereAreShippingMethods(TableNode $table)
    {
        foreach ($table->getHash() as $data) {
            $calculator = array_key_exists('calculator', $data) ? str_replace(' ', '_', strtolower($data['calculator'])) : DefaultCalculators::PER_ITEM_RATE;
            $configuration = array_key_exists('configuration', $data) ? $this->getConfiguration($data['configuration']) : null;

            if (!isset($data['enabled'])) {
                $data['enabled'] = 'yes';
            }

            $this->thereIsShippingMethod($data['name'], $data['zone'], $calculator, $configuration, 'yes' === $data['enabled'], false);
        }

        $this->getEntityManager()->flush();
    }

    /**
     * @Given /^I created shipping method "([^""]*)" within zone "([^""]*)"$/
     * @Given /^There is shipping method "([^""]*)" within zone "([^""]*)"$/
     */
    public function thereIsShippingMethod($name, $zoneName, $calculator = DefaultCalculators::PER_ITEM_RATE, array $configuration = null, $enabled = true, $flush = true)
    {
        /* @var $method ShippingMethodInterface */
        $method = $this
            ->getRepository('shipping_method')
            ->createNew()
        ;

        $method->setName($name);
        $method->setZone($this->findOneByName('zone', $zoneName));
        $method->setCalculator($calculator);
        $method->setConfiguration($configuration ?: array('amount' => 2500));
        $method->setEnabled($enabled);

        $manager = $this->getEntityManager();
        $manager->persist($method);
        if ($flush) {
            $manager->flush();
        }

        return $method;
    }

    /**
     * @Given /^the following locales are defined:$/
     * @Given /^there are following locales configured:$/
     */
    public function thereAreLocales(TableNode $table)
    {
        $repository = $this->getRepository('locale');
        $manager = $this->getEntityManager();

        foreach ($table->getHash() as $data) {
            $locale = $repository->createNew();
            $locale->setCode($data['code']);

            if (isset($data['enabled'])) {
                $locale->setEnabled('yes' === $data['enabled']);
            }

            $manager->persist($locale);
        }

        $manager->flush();
    }

    /**
     * @Given /^product "([^""]*)" is available in all variations$/
     */
    public function productIsAvailableInAllVariations($productName)
    {
        $product = $this->findOneByName('product', $productName);

        $this->getService('sylius.generator.product_variant')->generate($product);

        /* @var $variant ProductVariantInterface */
        foreach ($product->getVariants() as $variant) {
            $variant->setPrice($product->getMasterVariant()->getPrice());
        }

        $manager = $this->getEntityManager();
        $manager->persist($product);
        $manager->flush();
    }

    /**
     * Create an address instance from string.
     *
     * @param string $string
     *
     * @return AddressInterface
     */
    private function createAddress($string)
    {
        $addressData = explode(',', $string);
        $addressData = array_map('trim', $addressData);

        list($firstname, $lastname) = explode(' ', $addressData[0]);

        /* @var $address AddressInterface */
        $address = $this->getRepository('address')->createNew();
        $address->setFirstname(trim($firstname));
        $address->setLastname(trim($lastname));
        $address->setStreet($addressData[1]);
        $address->setPostcode($addressData[2]);
        $address->setCity($addressData[3]);
        $address->setCountry($this->findOneByName('country', $addressData[4]));

        return $address;
    }

    /**
     * Create an payment instance.
     *
     * @param OrderInterface         $order
     * @param PaymentMethodInterface $method
     */
    private function createPayment(OrderInterface $order, PaymentMethodInterface $method)
    {
        /** @var $payment PaymentInterface */
        $payment = $this->getRepository('payment')->createNew();
        $payment->setOrder($order);
        $payment->setMethod($method);
        $payment->setAmount($order->getTotal());
        $payment->setCurrency($order->getCurrency() ?: 'EUR');
        $payment->setState(PaymentInterface::STATE_COMPLETED);

        $order->addPayment($payment);
    }

    /**
     * Create an shipment instance from string.
     *
     * @param string $string
     *
     * @return ShipmentInterface
     */
    private function createShipment($string)
    {
        $shipmentData = explode(',', $string);
        $shipmentData = array_map('trim', $shipmentData);

        /* @var $shippingMethod ShippingMethodInterface */
        $shippingMethod = $this->getRepository('shipping_method')->findOneBy(array('name' => $shipmentData[0]));

        /* @var $shipment ShipmentInterface */
        $shipment = $this->getRepository('shipment')->createNew();
        $shipment->setMethod($shippingMethod);
        if (isset($shipmentData[1])) {
            $shipment->setState($shipmentData[1]);
        }
        if (isset($shipmentData[2])) {
            $shipment->setTracking($shipmentData[2]);
        }

        return $shipment;
    }

    /**
     * @Given /^I am logged in as administrator$/
     */
    public function iAmLoggedInAsAdministrator()
    {
        $this->iAmLoggedInAsRole(
            'ROLE_ADMIN',
            'admin_behat',
            'admin_behat@example.org',
            'admin_behat',
            'yes',
            array('Administrators')
        );
    }

    /**
     * @Given /^I am logged in student/
     * @Given /^I am logged in as student "([^""]*)"$/
     */
    public function iAmLoggedInStudent($email = 'student@example.com')
    {
        $this->iAmLoggedInAsRole(
            'ROLE_USER',
            'student',
            $email,
            'student',
            'yes',
            array('Students')
        );
    }

    /**
     * @Given /^I am logged in teacher/
     * @Given /^I am logged in as teacher "([^""]*)"$/
     */
    public function iAmLoggedInTeacher($email = 'teacher@example.com')
    {
        $this->iAmLoggedInAsRole(
            'ROLE_TEACHER',
            'teacher',
            $email,
            'teacher',
            'yes',
            array('Teachers')
        );
    }

    /**
     * @Given /^I am logged in user$/
     * @Given /^I am logged in as user "([^""]*)"$/
     */
    public function iAmLoggedInUser($email = 'user@example.com')
    {
        $this->iAmLoggedInAsRole('ROLE_USER', 'user', $email, 'user', 'yes');
    }

    /**
     * @Given /^I am not logged in$/
     */
    public function iAmNotLoggedIn()
    {
        $this->getSession()->visit($this->generatePageUrl('fos_user_security_logout'));
    }

    /**
     * @Given /^there are following users:$/
     */
    public function thereAreFollowingUsers(TableNode $table)
    {
        foreach ($table->getHash() as $data) {
            $this->thereIsUser(
                $data['username'],
                $data['email'],
                isset($data['plain_password']) ? $data['plain_password'] : $this->faker->word(),
                'ROLE_USER',
                isset($data['enabled']) ? $data['enabled'] : true,
                isset($data['groups']) && !empty($data['groups']) ? explode(',', $data['groups']) : array(),
                false
            );
        }

        $this->getEntityManager()->flush();
    }

    /**
     * Create user and login with given role.
     *
     * @param string $role
     * @param string $username
     * @param string $email
     * @param string $password
     * @param string $enabled
     * @param array $groups
     */
    private function iAmLoggedInAsRole(
        $role,
        $username = 'chamilo',
        $email = 'chamilo@example.com',
        $password = 'chamilo',
        $enabled = 'yes',
        $groups = array()
    ) {
        $this->getSession()->visit($this->generatePageUrl('fos_user_security_login'));

        $this->thereIsUser($username, $email, $password, $role, $enabled, $groups);

        $this->fillField('_username', $username);
        $this->fillField('_password', $password);
        $this->pressButton('_submit');
    }

    /**
     * @param string $username
     * @param string $email
     * @param string $password
     * @param string  $role
     * @param string $enabled
     * @param array $groups
     * @param bool $flush
     * @return User|\FOS\UserBundle\Model\UserInterface|object
     */
    public function thereIsUser(
        $username,
        $email,
        $password,
        $role = null,
        $enabled = 'yes',
        $groups = array(),
        $flush = true
    ) {
        $userManager = $this->getUserManager();
        $groupManager = $this->getGroupManager();
        $user = $userManager->findOneBy(array('email' => $email));

        if (null === $user) {

            /** @var User $user */
            $user = $userManager->createUser();
            $user->setUsername($username);
            $user->setFirstname($this->faker->firstName);
            $user->setLastname($this->faker->lastName);
            $user->setEmail($email);
            $user->setEnabled('yes' === $enabled);
            $user->setPlainPassword($password);
            $user->setLocked(false);

            if (null !== $role) {
                $user->addRole($role);
            }

            if (!empty($groups)) {
                foreach ($groups as $groupName) {
                    if ($group = $groupManager->findGroupByName($groupName)) {
                        $user->addGroup($group);
                    }
                }
            }

            $this->getEntityManager()->persist($user);

            if ($flush) {
                $this->getEntityManager()->flush();
            }
        }

        return $user;
    }

}
