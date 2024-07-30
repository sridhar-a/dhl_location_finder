<?php

namespace Drupal\dhl_location_finder\Form;

use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\Yaml\Yaml;

class DhlLocationForm extends FormBase {

  protected $httpClient;

  public function __construct(ClientInterface $http_client) {
    $this->httpClient = $http_client;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client')
    );
  }

  public function getFormId() {
    return 'dhl_location_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['country'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Country'),
      '#required' => TRUE,
    ];
    $form['city'] = [
      '#type' => 'textfield',
      '#title' => $this->t('City'),
      '#required' => TRUE,
    ];
    $form['postal_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Postal Code'),
      '#required' => TRUE,
    ];
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $country = $form_state->getValue('country');
    $city = $form_state->getValue('city');
    $postal_code = $form_state->getValue('postal_code');

    try {
      $response = $this->httpClient->request('GET', 'https://api.dhl.com/location-finder/v1/find-by-address', [
        'headers' => [
          'DHL-API-Key' => 'demo-key',
        ],
        'query' => [
          'countryCode' => $country,
          'postalCode' => $postal_code,
          'addressLocality' => $city
        ],
      ]);

      $data = json_decode($response->getBody(), TRUE);      

      // Process and filter data.
      $locations = $this->filterLocations($data['locations']);

      // Convert filtered locations to YAML format.
      $yaml_output = $this->convertLocationsToYaml($locations);

      // Display the YAML output.
      \Drupal::messenger()->addMessage(t('<pre>@locations</pre>', ['@locations' => $yaml_output]));
    } catch (Exception $e) {
      \Drupal::messenger()->addError(t('Failed to fetch locations: @message', ['@message' => $e->getMessage()]));
    }
  }

  protected function filterLocations($locations) {    
    $filtered = [];   
    foreach ($locations as $n=> &$location) {
      $address = $location['place']['address']['streetAddress'];
      $openingHours = $location['openingHours'];      

      $odd_address = preg_match('/\d/', $address) && (intval(preg_replace('/\D/', '', $address)) % 2 !== 0);      

      $all_working_days = [];
      foreach ($openingHours as $i=>$_openingHrs) {        
        $opens     = $_openingHrs['opens'];
        $closes    = $_openingHrs['closes'];        
        $dayOfWeek = $_openingHrs['dayOfWeek'];
        $day = explode("/", $dayOfWeek);
        $day = end($day);
        $day = strtolower($day);
        $all_working_days[$i] = $day;
        // if ('publicholidays' === $day) continue;        
        $location['openingHrs'][$day] = $opens . ' - ' . $closes;
      }
      $all_working_days = array_unique($all_working_days);   
      $open_weekends = in_array('saturday', $all_working_days) || in_array('sunday', $all_working_days);       

      if ($open_weekends && !$odd_address) {
        $filtered[] = $location;
      }

    }    
    return $filtered;
  }

  protected function convertLocationsToYaml($locations) {
    $yaml = [];
    foreach ($locations as $location) {
      $yaml[] = Yaml::dump([
        'locationName' => $location['name'],
        'address' => $location['place']['address'],
        'openingHours' => $location['openingHrs'],
      ], 2, 4, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
    }
    return implode("\n---\n", $yaml);
  }
}