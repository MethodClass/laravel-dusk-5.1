<?php

namespace Laravel\Dusk\Concerns;

use Exception;
use Illuminate\Support\Str;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverKeys;
use Facebook\WebDriver\Remote\LocalFileDetector;
use Facebook\WebDriver\Remote\UselessFileDetector;
use Facebook\WebDriver\Interactions\WebDriverActions;

/**
 * Trait InteractsWithElements
 *
 * @property \Facebook\WebDriver\Remote\RemoteWebDriver driver
 * @property \Laravel\Dusk\ElementResolver              resolver
 */
trait InteractsWithElements
{
    /**
     * Get all of the elements matching the given selector.
     *
     * @param string $selector
     *
     * @return array
     */
    public function elements($selector)
    {
        return $this->resolver->all($selector);
    }

    /**
     * Get the element matching the given selector.
     *
     * @param string $selector
     *
     * @return \Facebook\WebDriver\Remote\RemoteWebElement|null
     */
    public function element($selector)
    {
        return $this->resolver->find($selector);
    }

    /**
     * Click the element at the given selector.
     *
     * @param string $selector
     *
     * @return $this
     */
    public function click($selector)
    {
        $this->resolver->findOrFail($selector)->click();

        return $this;
    }

    /**
     * Right click the element at the given selector.
     *
     * @param string $selector
     *
     * @return $this
     */
    public function rightClick($selector)
    {
        (new WebDriverActions($this->driver))->contextClick(
            $this->resolver->findOrFail($selector)
        )->perform();

        return $this;
    }

    /**
     * Click the link with the given text.
     *
     * @param string $link
     *
     * @return $this
     */
    public function clickLink($link)
    {
        $this->ensurejQueryIsAvailable();

        $selector = trim($this->resolver->format("a:contains({$link})"));

        $this->driver->executeScript("jQuery.find(\"{$selector}\")[0].click();");

        return $this;
    }

    /**
     * Directly get or set the value attribute of an input field.
     *
     * @param string      $selector
     * @param string|null $value
     *
     * @return $this|string
     */
    public function value($selector, $value = null)
    {
        if (is_null($value)) {
            return $this->resolver->findOrFail($selector)->getAttribute('value');
        }

        $selector = $this->resolver->format($selector);

        $this->driver->executeScript(
            "document.querySelector('{$selector}').value = '{$value}';"
        );

        return $this;
    }

    /**
     * Get the text of the element matching the given selector.
     *
     * @param string $selector
     *
     * @return string
     */
    public function text($selector)
    {
        return $this->resolver->findOrFail($selector)->getText();
    }

    /**
     * Get the given attribute from the element matching the given selector.
     *
     * @param string $selector
     * @param string $attribute
     *
     * @return string
     */
    public function attribute($selector, $attribute)
    {
        return $this->resolver->findOrFail($selector)->getAttribute($attribute);
    }

    /**
     * Send the given keys to the element matching the given selector.
     *
     * @param string $selector
     * @param array ...$keys
     *
     * @return $this
     */
    public function keys($selector, ...$keys)
    {
        $this->resolver->findOrFail($selector)->sendKeys($this->parseKeys($keys));

        return $this;
    }

    /**
     * Parse the keys before sending to the keyboard.
     *
     * @param array $keys
     *
     * @return array
     */
    protected function parseKeys($keys)
    {
        return collect($keys)->map(function ($key) {
            if (is_string($key) && Str::startsWith($key, '{') && Str::endsWith($key, '}')) {
                $key = constant(WebDriverKeys::class.'::'.strtoupper(trim($key, '{}')));
            }

            if (is_array($key) && Str::startsWith($key[0], '{')) {
                $key[0] = constant(WebDriverKeys::class.'::'.strtoupper(trim($key[0], '{}')));
            }

            return $key;
        })->all();
    }

    /**
     * Type the given value in the given field.
     *
     * @param string $field
     * @param string $value
     *
     * @throws \Exception
     *
     * @return $this
     */
    public function type($field, $value)
    {
        $this->resolver->resolveForTyping($field)->clear()->sendKeys($value);

        return $this;
    }

    /**
     * Set the content in a wysiwyg editor
     *
     * @param string $type
     * @param string $id  ID of wysiwyg, without jQuery format
     * @param string $value
     *
     * @throws \Exception
     * @throws \Facebook\WebDriver\Exception\TimeOutException
     *
     * @return $this
     */
    public function wysiwyg($type, $id, $value)
    {
        switch ($type) {
            case 'tinymce':
                $this->waitFor('#'.$id.'_ifr');
                $value = str_replace(['"', '\''], ['&quot;', '&#039'], $value);
                $this->driver->executeScript("tinyMCE.get('$id').setContent('$value');");
                break;
            default:
                throw new Exception('Unsupported wysiwyg');
        }

        return $this;
    }

    /**
     * Type the given value in the given field without clearing it.
     *
     * @param string $field
     * @param string $value
     *
     * @throws \Exception
     *
     * @return $this
     */
    public function append($field, $value)
    {
        $this->resolver->resolveForTyping($field)->sendKeys($value);

        return $this;
    }

    /**
     * Clear the given field.
     *
     * @param string $field
     *
     * @throws \Exception
     *
     * @return $this
     */
    public function clear($field)
    {
        $this->resolver->resolveForTyping($field)->clear();

        return $this;
    }

    /**
     * Select the given value or random value of a drop-down field.
     *
     * @param string $field
     * @param string $value
     *
     * @throws \Exception
     *
     * @return $this
     */
    public function select($field, $value = null)
    {
        $element = $this->resolver->resolveForSelection($field);

        $options = $element->findElements(WebDriverBy::tagName('option'));

        if (is_null($value)) {
            $options[array_rand($options)]->click();
        } else {
            foreach ($options as $option) {
                if ((string) $option->getAttribute('value') === (string) $value) {
                    $option->click();

                    break;
                }
            }
        }

        return $this;
    }

    /**
     * Select the given value or random value of a drop-down field.
     *
     * @param string $selector
     * @param string $value
     *
     * @throws \Exception
     *
     * @return $this
     */
    public function selectBySelector($selector, $value = null)
    {
        $element = $this->resolver->firstOrFail($selector);

        $options = $element->findElements(WebDriverBy::tagName('option'));

        if (is_null($value)) {
            $options[array_rand($options)]->click();
        } else {
            foreach ($options as $option) {
                if ((string) $option->getAttribute('value') === (string) $value) {
                    $option->click();

                    break;
                }
            }
        }

        return $this;
    }

    /**
     * Select the given value or random value of a drop-down field using Select2.
     *
     * @param string $field       selector, or @element
     * @param array|string $value option value, may be multiple, eg. ['foo', 'bar']
     * @param int $wait           count of seconds for ajax loading
     *
     * @throws \Facebook\WebDriver\Exception\TimeOutException
     *
     * @return \Laravel\Dusk\Concerns\Browser|\Laravel\Dusk\Concerns\InteractsWithElements
     */
    public function select2($field, $value = null, $wait = 2)
    {
        $this->click($field);

        // if $value equal null, find random element and click him.
        if ($value === null) {
            $this->waitFor('.select2-results__options .select2-results__option--highlighted');
            $this->script(implode('', [
                "var _dusk_s2_elements = document.querySelectorAll('.select2-results__options .select2-results__option');",
                "document.querySelector('.select2-results__options .select2-results__option--highlighted').classList.remove('select2-results__option--highlighted');",
                'var _dusk_s2_el = _dusk_s2_elements[Math.floor(Math.random()*(_dusk_s2_elements.length - 1))];',
                "_dusk_s2_el.classList.add('select2-results__option--highlighted');",
            ]));
            $this->click('.select2-results__option--highlighted');

            return $this;
        }

        // check if search field exists and fill it.
        if ($element = $this->element('.select2-container .select2-search__field')) {
            foreach ((array) $value as $item) {
                $element->sendKeys($item);
                sleep($wait);
                $this->click('.select2-results__option--highlighted');
            }

            return $this;
        }

        // another way - w/o search field.
        $this->script("jQuery.find(\".select2-results__options .select2-results__option:contains('{$value}')\")[0].click()");

        return $this;
    }

    /**
     * Select the given value of a radio button field.
     *
     * @param string $field
     * @param string $value
     *
     * @throws \Exception
     *
     * @return $this
     */
    public function radio($field, $value)
    {
        $this->resolver->resolveForRadioSelection($field, $value)->click();

        return $this;
    }

    /**
     * Check the given checkbox.
     *
     * @param string $field
     * @param string $value
     *
     * @throws \Exception
     *
     * @return $this
     */
    public function check($field, $value = null)
    {
        $element = $this->resolver->resolveForChecking($field, $value);

        if (! $element->isSelected()) {
            $element->click();
        }

        return $this;
    }

    /**
     * Uncheck the given checkbox.
     *
     * @param string $field
     * @param string $value
     *
     * @throws \Exception
     *
     * @return $this
     */
    public function uncheck($field, $value = null)
    {
        $element = $this->resolver->resolveForChecking($field, $value);

        if ($element->isSelected()) {
            $element->click();
        }

        return $this;
    }

    /**
     * Attach the given file to the field.
     *
     * @param string $field
     * @param string $path
     *
     * @throws \Exception
     *
     * @return $this
     */
    public function attach($field, $path)
    {
        $element = $this->resolver->resolveForAttachment($field);

        if ($this->driver->getCapabilities()->getBrowserName() == 'phantomjs') {
            $element->setFileDetector(new UselessFileDetector())->sendKeys($path);
        } else {
            $element->setFileDetector(new LocalFileDetector())->sendKeys($path);
        }

        return $this;
    }

    /**
     * Press the button with the given text or name.
     *
     * @param string $button
     *
     * @throws \InvalidArgumentException
     *
     * @return $this
     */
    public function press($button)
    {
        $this->resolver->resolveForButtonPress($button)->click();

        return $this;
    }

    /**
     * Press the button with the given text or name.
     *
     * @param string $button
     * @param int $seconds
     *
     * @throws \InvalidArgumentException
     * @throws \Facebook\WebDriver\Exception\TimeOutException
     *
     * @return $this
     */
    public function pressAndWaitFor($button, $seconds = 5)
    {
        $element = $this->resolver->resolveForButtonPress($button);

        $element->click();

        return $this->waitUsing($seconds, 100, function () use ($element) {
            return $element->isEnabled();
        });
    }

    /**
     * Drag an element to another element using selectors.
     *
     * @param string $from
     * @param string $to
     *
     * @return $this
     */
    public function drag($from, $to)
    {
        (new WebDriverActions($this->driver))->dragAndDrop(
            $this->resolver->findOrFail($from),
            $this->resolver->findOrFail($to)
        )->perform();

        return $this;
    }

    /**
     * Drag an element up.
     *
     * @param string $selector
     * @param int    $offset
     *
     * @return $this
     */
    public function dragUp($selector, $offset)
    {
        return $this->dragOffset($selector, 0, -$offset);
    }

    /**
     * Drag an element down.
     *
     * @param string $selector
     * @param int    $offset
     *
     * @return $this
     */
    public function dragDown($selector, $offset)
    {
        return $this->dragOffset($selector, 0, $offset);
    }

    /**
     * Drag an element to the left.
     *
     * @param string $selector
     * @param int    $offset
     *
     * @return $this
     */
    public function dragLeft($selector, $offset)
    {
        return $this->dragOffset($selector, -$offset, 0);
    }

    /**
     * Drag an element to the right.
     *
     * @param string $selector
     * @param int    $offset
     *
     * @return $this
     */
    public function dragRight($selector, $offset)
    {
        return $this->dragOffset($selector, $offset, 0);
    }

    /**
     * Drag an element by the given offset.
     *
     * @param string $selector
     * @param int    $x
     * @param int    $y
     *
     * @return $this
     */
    public function dragOffset($selector, $x = 0, $y = 0)
    {
        (new WebDriverActions($this->driver))->dragAndDropBy(
            $this->resolver->findOrFail($selector),
            $x,
            $y
        )->perform();

        return $this;
    }

    /**
     * Accept a JavaScript dialog.
     *
     * @return $this
     */
    public function acceptDialog()
    {
        $this->driver->switchTo()->alert()->accept();

        return $this;
    }

    /**
     * Dismiss a JavaScript dialog.
     *
     * @return $this
     */
    public function dismissDialog()
    {
        $this->driver->switchTo()->alert()->dismiss();

        return $this;
    }
}
