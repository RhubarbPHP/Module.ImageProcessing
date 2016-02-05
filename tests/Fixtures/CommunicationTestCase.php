<?php

namespace Rhubarb\Scaffolds\Communications\Tests\Fixtures;

use Codeception\TestCase\Test;
use Rhubarb\Crown\Email\SimpleEmail;
use Rhubarb\Scaffolds\Communications\EmailProviders\CommunicationEmailProvider;
use Rhubarb\Scaffolds\Communications\Models\Communication;
use Rhubarb\Scaffolds\Communications\Models\CommunicationEmail;

abstract class CommunicationTestCase extends Test
{
    protected function __before()
    {
        parent::_before();

        Communication::clearObjectCache();
        CommunicationEmail::clearObjectCache();
    }

    /**
     * @return Communication
     * @throws \Rhubarb\Stem\Exceptions\RecordNotFoundException
     */
    public function createCommunicationForEmail()
    {
        $email = new SimpleEmail();
        $email->setSubject("The three billy goats");
        $email->addRecipient("john.smith@outlook.com", "John Smith");
        $email->setSender("jane.smith@outlook.com", "Jane Smith" );
        $email->setText("Michael went to mow, went to mow a meadow.");
        $email->setHtml("<p>Michael went to mow, went to mow a meadow.</p>");

        return Communication::fromEmail($email);
    }
}
