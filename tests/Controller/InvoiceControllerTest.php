<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Controller;

use App\Entity\Invoice;
use App\Entity\InvoiceTemplate;
use App\Entity\Timesheet;
use App\Entity\User;
use App\Tests\DataFixtures\InvoiceTemplateFixtures;
use App\Tests\DataFixtures\TimesheetFixtures;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * @group integration
 */
class InvoiceControllerTest extends ControllerBaseTest
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->clearInvoiceFiles();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->clearInvoiceFiles();
    }

    private function clearInvoiceFiles()
    {
        $path = __DIR__ . '/../_data/invoices/';

        if (is_dir($path)) {
            $files = glob($path . '*');
            foreach ($files as $file) {
                unlink($file);
            }
        }
    }

    public function testIsSecure()
    {
        $this->assertUrlIsSecured('/invoice/');
    }

    public function testIsSecureForRole()
    {
        $this->assertUrlIsSecuredForRole(User::ROLE_USER, '/invoice/');
    }

    public function testIndexActionRedirectsToCreateTemplate()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);

        $this->request($client, '/invoice/');
        $this->assertIsRedirect($client, '/invoice/template/create');
    }

    public function testIndexActionHasErrorMessageOnEmptyQuery()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);

        $fixture = new InvoiceTemplateFixtures();
        $templates = $this->importFixture($fixture);
        $id = $templates[0]->getId();

        $this->request($client, '/invoice/?customers[]=1&template=' . $id);
        $this->assertTrue($client->getResponse()->isSuccessful());

        $this->assertHasNoEntriesWithFilter($client);
    }

    public function testListTemplateAction()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);

        $fixture = new InvoiceTemplateFixtures();
        $this->importFixture($fixture);

        $this->request($client, '/invoice/template');

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertHasDataTable($client);
    }

    public function testCreateTemplateAction()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $this->request($client, '/invoice/template/create');
        $this->assertTrue($client->getResponse()->isSuccessful());

        $form = $client->getCrawler()->filter('form[name=invoice_template_form]')->form();
        $client->submit($form, [
            'invoice_template_form' => [
                'name' => 'Test',
                'title' => 'Test invoice template',
                'company' => 'Company name',
                'renderer' => 'default',
                'calculator' => 'default',
            ]
        ]);

        $this->assertIsRedirect($client, $this->createUrl('/invoice/template'));
        $client->followRedirect();
        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertHasFlashSuccess($client);
    }

    public function testCopyTemplateAction()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);

        $fixture = new InvoiceTemplateFixtures();
        $templates = $this->importFixture($fixture);
        /** @var InvoiceTemplate $template */
        $template = $templates[0];

        $this->request($client, '/invoice/template/create/' . $template->getId());
        $this->assertTrue($client->getResponse()->isSuccessful());

        $form = $client->getCrawler()->filter('form[name=invoice_template_form]')->form();
        $values = $form->getPhpValues()['invoice_template_form'];
        $this->assertEquals($template->getName() . ' (1)', $values['name']);
        $this->assertEquals($template->getTitle(), $values['title']);
        $this->assertEquals($template->getDueDays(), $values['dueDays']);
        $this->assertEquals($template->getCalculator(), $values['calculator']);
        $this->assertEquals($template->getVat(), $values['vat']);
        $this->assertEquals($template->getRenderer(), $values['renderer']);
        $this->assertEquals($template->getCompany(), $values['company']);
        $this->assertEquals($template->getAddress(), $values['address']);
        $this->assertEquals($template->getPaymentTerms(), $values['paymentTerms']);
    }

    public function testCreateAction()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);

        $fixture = new InvoiceTemplateFixtures();
        $templates = $this->importFixture($fixture);
        /** @var InvoiceTemplate $template */
        $template = $templates[0];

        $begin = new \DateTime('first day of this month');
        $end = new \DateTime('last day of this month');
        $fixture = new TimesheetFixtures();
        $fixture
            ->setUser($this->getUserByRole(User::ROLE_TEAMLEAD))
            ->setAmount(20)
            ->setStartDate($begin)
        ;
        $timesheets = $this->importFixture($fixture);
        foreach ($timesheets as $timesheet) {
            $this->assertFalse($timesheet->isExported());
        }

        $this->request($client, '/invoice/');
        $this->assertTrue($client->getResponse()->isSuccessful());

        $dateRange = $this->formatDateRange($begin, $end);

        $form = $client->getCrawler()->filter('#invoice-print-form')->form();
        $node = $form->getFormNode();
        $node->setAttribute('action', $this->createUrl('/invoice/'));
        $node->setAttribute('method', 'GET');
        $client->submit($form, [
            'template' => $template->getId(),
            'daterange' => $dateRange,
            'customers' => [1],
        ]);

        $this->assertTrue($client->getResponse()->isSuccessful());

        // no warning should be displayed
        $node = $client->getCrawler()->filter('div.callout.callout-warning.lead');
        $this->assertEquals(0, $node->count());
        // but the datatable with all timesheets
        $this->assertDataTableRowCount($client, 'datatable_invoice_create', 20);

        $urlParams = [
            'daterange' => $dateRange,
            'projects[]' => 1,
            'template' => $template->getId(),
        ];

        $token = $client->getCrawler()->filter('div#create-token')->attr('data-value');

        $action = '/invoice/save-invoice/1/' . $token . '?' . http_build_query($urlParams);
        $this->request($client, $action);
        $this->assertIsRedirect($client, '/invoice/show?id=', false);
        $client->followRedirect();
        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertDataTableRowCount($client, 'datatable_invoices', 1);

        $em = $this->getEntityManager();
        $em->clear();
        $timesheets = $em->getRepository(Timesheet::class)->findAll();
        $this->assertCount(20, $timesheets);
        /** @var Timesheet $timesheet */
        foreach ($timesheets as $timesheet) {
            $this->assertTrue($timesheet->isExported());
        }
    }

    public function testPreviewAction()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);

        $fixture = new InvoiceTemplateFixtures();
        $templates = $this->importFixture($fixture);
        $id = $templates[0]->getId();

        $begin = new \DateTime('first day of this month');
        $end = new \DateTime('last day of this month');
        $fixture = new TimesheetFixtures();
        $fixture
            ->setUser($this->getUserByRole(User::ROLE_TEAMLEAD))
            ->setAmount(20)
            ->setStartDate($begin)
        ;
        $this->importFixture($fixture);

        $this->request($client, '/invoice/');
        $this->assertTrue($client->getResponse()->isSuccessful());

        $dateRange = $this->formatDateRange($begin, $end);

        $form = $client->getCrawler()->filter('#invoice-print-form')->form();
        $node = $form->getFormNode();
        $node->setAttribute('action', $this->createUrl('/invoice/'));
        $node->setAttribute('method', 'GET');
        $client->submit($form, [
            'template' => $id,
            'daterange' => $dateRange,
            'customers' => [1],
        ]);

        $this->assertTrue($client->getResponse()->isSuccessful());

        $params = [
            'daterange' => $dateRange,
            'projects' => [1],
            'template' => $id,
            'customers[]' => 1
        ];

        $token = $client->getCrawler()->filter('div#preview-token')->attr('data-value');
        $action = '/invoice/preview/1/' . $token . '?' . http_build_query($params);

        $this->request($client, $action);
        $this->assertTrue($client->getResponse()->isSuccessful());
        $node = $client->getCrawler()->filter('body');
        $this->assertEquals(1, $node->count());

        /** @var \DOMElement $element */
        $element = $node->getIterator()[0];
        $this->assertEquals('invoice_print', $element->getAttribute('class'));
    }

    public function testCreateActionAsAdminWithDownloadAndStatusChange()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);

        $fixture = new InvoiceTemplateFixtures();
        $templates = $this->importFixture($fixture);
        $template = $templates[0];

        $begin = new \DateTime('first day of this month');
        $end = new \DateTime('last day of this month');
        $fixture = new TimesheetFixtures();
        $fixture
            ->setUser($this->getUserByRole(User::ROLE_ADMIN))
            ->setAmount(20)
            ->setStartDate($begin)
        ;
        $this->importFixture($fixture);

        $this->request($client, '/invoice/');
        $this->assertTrue($client->getResponse()->isSuccessful());

        $dateRange = $this->formatDateRange($begin, $end);

        $form = $client->getCrawler()->filter('#invoice-print-form')->form();
        $node = $form->getFormNode();
        $node->setAttribute('action', $this->createUrl('/invoice/'));
        $node->setAttribute('method', 'GET');
        $client->submit($form, [
            'template' => $template->getId(),
            'daterange' => $dateRange,
            'customers' => [1],
        ]);

        $this->assertTrue($client->getResponse()->isSuccessful());

        // no warning should be displayed
        $node = $client->getCrawler()->filter('div.callout.callout-warning.lead');
        $this->assertEquals(0, $node->count());
        // but the datatable with all timesheets
        $this->assertDataTableRowCount($client, 'datatable_invoice_create', 20);

        $token = $client->getCrawler()->filter('div#create-token')->attr('data-value');

        $form = $client->getCrawler()->filter('#invoice-print-form')->form();
        $node = $form->getFormNode();
        $node->setAttribute('action', $this->createUrl('/invoice/save-invoice/1/' . $token));
        $node->setAttribute('method', 'GET');
        $client->submit($form, [
            'template' => $template->getId(),
            'daterange' => $dateRange,
            'projects' => [1],
        ]);

        $this->assertIsRedirect($client, '/invoice/show?id=', false);
        $client->followRedirect();
        $this->assertTrue($client->getResponse()->isSuccessful());

        $invoices = $this->getEntityManager()->getRepository(Invoice::class)->findAll();
        self::assertCount(1, $invoices);
        $id = $invoices[0]->getId();

        $this->assertHasFlashSuccess($client);

        $this->assertHasDataTable($client);
        $this->assertDataTableRowCount($client, 'datatable_invoices', 1);

        // make sure the invoice is saved
        $this->request($client, '/invoice/download/' . $id);
        $response = $client->getResponse();
        $this->assertTrue($response->isSuccessful());
        self::assertInstanceOf(BinaryFileResponse::class, $response);
        self::assertFileExists($response->getFile());

        $this->request($client, '/invoice/show');
        $this->assertTrue($client->getResponse()->isSuccessful());
        $link = $client->getCrawler()->selectLink('Waiting for payment');

        $this->request($client, $link->attr('href'));
        $this->assertIsRedirect($client, '/invoice/show');
        $client->followRedirect();
        $this->assertTrue($client->getResponse()->isSuccessful());

        $link = $client->getCrawler()->selectLink('Invoice paid');
        $url = $link->attr('href');
        $this->request($client, $url);
        $this->assertTrue($client->getResponse()->isSuccessful());

        $this->assertHasValidationError(
            $client,
            $url,
            'form[name=invoice_edit_form]',
            [
                'invoice_edit_form' => [
                    'paymentDate' => 'invalid'
                ]
            ],
            ['#invoice_edit_form_paymentDate']
        );

        $this->assertTrue($client->getResponse()->isSuccessful());

        $form = $client->getCrawler()->filter('form[name=invoice_edit_form]')->form();
        $client->submit($form, [
            'invoice_edit_form' => [
                'paymentDate' => (new \DateTime())->format(self::DEFAULT_DATE_FORMAT)
            ]
        ]);

        $this->assertIsRedirect($client, '/invoice/show');
        $client->followRedirect();
        $this->assertTrue($client->getResponse()->isSuccessful());

        $token = $this->getCsrfToken($client, 'invoice.status');
        $this->request($client, '/invoice/change-status/' . $id . '/new/' . $token->getValue());
        $this->assertIsRedirect($client, '/invoice/show');
        $client->followRedirect();
        $this->assertTrue($client->getResponse()->isSuccessful());
    }

    public function testEditTemplateAction()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);

        $fixture = new InvoiceTemplateFixtures();
        $template = $this->importFixture($fixture);
        $id = $template[0]->getId();

        $this->request($client, '/invoice/template/' . $id . '/edit?page=1');
        $form = $client->getCrawler()->filter('form[name=invoice_template_form]')->form();
        $client->submit($form, [
            'invoice_template_form' => [
                'name' => 'Test 2!',
                'title' => 'Test invoice template',
                'company' => 'Company name',
                'renderer' => 'default',
                'calculator' => 'default',
            ]
        ]);

        $this->assertIsRedirect($client, $this->createUrl('/invoice/template'));
        $client->followRedirect();
        $this->assertTrue($client->getResponse()->isSuccessful());

        $this->assertHasFlashSuccess($client);
    }

    public function testDeleteTemplateAction()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);

        $fixture = new InvoiceTemplateFixtures();
        $template = $this->importFixture($fixture);
        $id = $template[0]->getId();

        $this->request($client, '/invoice/template');
        $url = $this->createUrl('/invoice/template/' . $id . '/delete/');
        $links = $client->getCrawler()->filterXPath("//a[starts-with(@href, '" . $url . "')]");

        $this->requestPure($client, $links->attr('href'));
        $this->assertIsRedirect($client, '/invoice/template');
        $client->followRedirect();

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertHasFlashSuccess($client);

        $this->assertEquals(0, $this->getEntityManager()->getRepository(InvoiceTemplate::class)->count([]));
    }

    public function testUploadDocumentAction()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_SUPER_ADMIN);

        $fixture = new InvoiceTemplateFixtures();
        $this->importFixture($fixture);

        $this->request($client, '/invoice/document_upload');
        $this->assertTrue($client->getResponse()->isSuccessful());

        $node = $client->getCrawler()->filter('form[name=invoice_document_upload_form]');
        self::assertEquals(1, $node->count(), 'Could not find upload form');
        // we do not test the upload here, just make sure that the action can be rendered properly
    }

    public function testExportIsSecureForRole()
    {
        $this->assertUrlIsSecuredForRole(User::ROLE_USER, '/invoice/export');
    }

    public function testExportAction()
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);
        $this->assertAccessIsGranted($client, '/invoice/export');
        $this->assertExcelExportResponse($client, 'kimai-invoices_');
    }
}
