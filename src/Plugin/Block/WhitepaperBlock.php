<?php

namespace Drupal\iq_whitepaper\Plugin\Block;

use Drupal\iq_whitepaper\Form\WhitepaperForm;
use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'Whitepaper' Block.
 *
 * @Block(
 *   id = "whitepaper_block",
 *   admin_label = @Translation("Whitepaper block"),
 *   category = @Translation("Forms"),
 * )
 */
class WhitepaperBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return \Drupal::formBuilder()->getForm(WhitepaperForm::class);
  }

}
