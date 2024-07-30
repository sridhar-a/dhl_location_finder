<?php

namespace Drupal\Tests\dhl_location_finder\Functional;

use Drupal\Tests\BrowserTestBase;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests the DHL Location Finder form.
 *
 * @group dhl_location_finder
 */
class DhlLocationFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dhl_location_finder'];

  /**
   * The default theme used during testing.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests the form submission.
   */
  public function testFormSubmission() {
    // Create a user with permission to access content.
    $user = $this->drupalCreateUser(['access content']);
    $this->drupalLogin($user);

    // Go to the form page.
    $this->drupalGet('/dhl-location-form');
    $this->assertSession()->statusCodeEquals(200);

    // Fill out and submit the form.
    $this->submitForm([
      'country' => 'CZ',
      'city' => 'Prague',
      'postal_code' => '11000',
    ], 'Submit');

    // Check that the result is displayed.
    // DEBUG: Print the page content to see what is being returned.
    // $page_content = $this->getSession()->getPage()->getContent();
    // print $page_content;

    // Check for the expected text.
    // $this->assertSession()->pageTextContains('Packstation 103');
    $this->assertSession()->pageTextContains('AlzaBox P1 - Nove Mesto (Galerie Mysak)');
  }

  /**
   * Logs in a user using the login form.
   */
  protected function drupalLogin($account) {
    $this->drupalGet('user/login');
    $this->submitForm([
      'name' => $account->getAccountName(),
      'pass' => $account->passRaw,
    ], t('Log in'));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains(t('Sorry, unrecognized username or password.'));
  } 

}
