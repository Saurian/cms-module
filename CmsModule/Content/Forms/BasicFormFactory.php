<?php

/**
 * This file is part of the Venne:CMS (https://github.com/Venne)
 *
 * Copyright (c) 2011, 2012 Josef Kříž (http://www.josef-kriz.cz)
 *
 * For the full copyright and license information, please view
 * the file license.txt that was distributed with this source code.
 */

namespace CmsModule\Content\Forms;

use CmsModule\Content\Entities\PageEntity;
use DoctrineModule\Forms\FormFactory;
use DoctrineModule\Repositories\BaseRepository;
use Nette\Forms\Controls\SelectBox;
use Venne\Forms\Form;

/**
 * @author Josef Kříž <pepakriz@gmail.com>
 */
class BasicFormFactory extends FormFactory
{

	protected function getControlExtensions()
	{
		return array(
			new \DoctrineModule\Forms\ControlExtensions\DoctrineExtension(),
			new \FormsModule\ControlExtensions\ControlExtension(),
		);
	}


	/**
	 * @param Form $form
	 */
	public function configure(Form $form)
	{

		$infoGroup = $form->addGroup('Informations');

		$form->addText('name', 'Name');
		$form['name']->addRule($form::FILLED);

		// route
		$mainRoute = $form->addOne('mainRoute');
		$mainRoute->setCurrentGroup($infoGroup);

		$mainRoute->addHidden('localUrlTemplate')
			->getControlPrototype()->class[] = 'localUrlTemplate';

		$input = \Nette\Utils\Html::el('input');
		$input->id = 'form-checkbox';
		$input->type = 'checkbox';
		$htmlUrl = \Nette\Utils\Html::el('span');
		$htmlUrl->setHtml(' <label>' . $input . ' ' . ($form->getTranslator() ? $form->getTranslator()->translate('autogenerated') : 'autogenerated') . '</label>');

		$input = \Nette\Utils\Html::el('input');
		$input->id = 'form-checkbox-title';
		$input->type = 'checkbox';
		$htmlTitle = \Nette\Utils\Html::el('span');
		$htmlTitle->setHtml(' <label>' . $input . ' ' . ($form->getTranslator() ? $form->getTranslator()->translate('autogenerated') : 'autogenerated') . '</label>');

		$mainRoute->addText('localUrl', 'URL')
			->addRule($form::REGEXP, "URL can not contain '/'", "/^[a-zA-z0-9._-]*$/")
			->addRule($form::FILLED);
		$mainRoute['localUrl']
			->setOption('description', $htmlUrl)
			->getControlPrototype()->class[] = 'localUrl';


		$mainRoute->setCurrentGroup($form->addGroup());

		$mainRoute->addText('title', 'Title')
			->setOption('description', $htmlTitle)
			->getControlPrototype()->class[] = 'formTitle';

		// tags
		if (!$form->data->translationFor) {
			$tag = $form->addSelect('tag', 'Page type', array_merge(array(FALSE => 'Normal page'), PageEntity::getTags()));
		}

		// parent
		if (!$form->data->translationFor) {
			if ($form->data->parent) {
				/** @var $parent SelectBox */
				$form->addGroup()->setOption('id', 'parent-group');
				$form->addManyToOne("parent", "Parent content", NULL, NULL, array("translationFor" => NULL))->setPrompt(NULL);

				$tag->addCondition($form::EQUAL, FALSE)->toggle('parent-group');
			}
		}

		$mainRoute->setCurrentGroup($form->addGroup('Layout'));

		if ($form->data->parent) {
			$mainRoute->addCheckbox('copyLayoutFromParent', 'Layout from parent');
		} else {
			$mainRoute->addHidden('copyLayoutFromParent', 'Layout from parent');
		}
		$mainRoute['copyLayoutFromParent']->addCondition($form::EQUAL, FALSE)->toggle('group-layout_' . $form->data->id);

		if ($form->data->parent) {
			$mainRoute->setCurrentGroup($form->getForm()->addGroup()->setOption('id', 'group-layout_' . $form->data->id));
		} else {
			$mainRoute->setCurrentGroup($form->getForm()->addGroup('Layout')->setOption('id', 'group-layout_' . $form->data->id));
		}
		$mainRoute->addManyToOne('layout', 'Layout');

		$mainRoute->setCurrentGroup($form->addGroup());
		$mainRoute->addCheckbox('copyLayoutToChildren', 'Share layout with children');
		$mainRoute['copyLayoutToChildren']->addCondition($form::EQUAL, FALSE)->toggle('group-layout2_' . $form->data->id);

		$mainRoute->setCurrentGroup($form->getForm()->addGroup()->setOption('id', 'group-layout2_' . $form->data->id));
		$mainRoute->addManyToOne('childrenLayout', 'Share new layout');

		// Navigation
		$form->addGroup('Navigation');
		$form->addCheckbox('navigationShow', 'Show in navigation')->addCondition($form::EQUAL, TRUE)->toggle('form-navigation-own');
		$form->addGroup()->setOption('id', 'form-navigation-own');
		$form->addCheckbox('navigationOwn', 'Use own title')->addCondition($form::EQUAL, TRUE)->toggle('form-navigation-title');
		$form->addGroup()->setOption('id', 'form-navigation-title');
		$form->addText('navigationTitleRaw', 'Navigation title');

		// languages
		/** @var $repository \DoctrineModule\Repositories\BaseRepository */
		$repository = $form->mapper->entityManager->getRepository('CmsModule\Content\Entities\LanguageEntity');
		if ($repository->createQueryBuilder('a')->select('COUNT(a)')->getQuery()->getSingleScalarResult() > 1) {
			$form->addGroup("Languages");
			$form->addManyToMany("languages", "Content is in")->addRule($form::FILLED, 'Page must contain some language');
		}

		$form->addGroup();
		$form->addSaveButton('Save');

		$js = '
        	function detectAuto() {
				$("#' . $form['name']->getHtmlId() . '").unbind("keyup keydown blur");
				$("#' . $form['name']->getHtmlId() . '").stringToSlug({getPut: ".localUrlTemplate", space: "-" });

				$("#' . $form['name']->getHtmlId() . '").trigger("keyup");

				if($("#form-checkbox").is(":checked")) {
		            $("#' . $form['name']->getHtmlId() . '").stringToSlug({getPut: ".localUrl", space: "-" });
				}

				if($("#form-checkbox-title").is(":checked")) {
		            $("#' . $form['name']->getHtmlId() . '").bind("keydown keyup blur", function(){
		            	$(".formTitle").val($(this).val());
		            });
				}
			}

			function detectCheckbox() {
			    if($("#' . $form['mainRoute']['localUrlTemplate']->getHtmlId() . '").val() == $(".localUrl").val()){
					$("#form-checkbox").attr("checked", true);
				}

				if($("#' . $form['name']->getHtmlId() . '").val() == $(".formTitle").val()){
					$("#form-checkbox-title").attr("checked", true);
				}
			}

			$(document).ready( function() {
				detectAuto();
				detectCheckbox();
				detectAuto();

				$(".localUrl").bind("keydown keyup blur", function(){
					$("#form-checkbox").attr("checked", false);
					detectCheckbox();
					detectAuto();
				});

				$(".formTitle").bind("keydown keyup blur", function(){
					$("#form-checkbox-title").attr("checked", false);
					detectCheckbox();
					detectAuto();
				});

				$("#form-checkbox").live("click", function(event){
				    detectAuto();
				    $("#' . $form['name']->getHtmlId() . '").trigger("keyup");
				});

				$("#form-checkbox-title").live("click", function(){
				    detectAuto();
				    $("#' . $form['name']->getHtmlId() . '").trigger("keyup");
				});
			});
		';

		$script = \Nette\Utils\Html::el('script');
		$script->setHtml($js);
		$form['name']->setOption('description', $script);
	}


	public function handleSave(Form $form)
	{
		if ($form['navigationOwn']->value) {
			$form->data->navigationTitleRaw = $form['navigationTitleRaw']->value;
		} else {
			$form->data->navigationTitleRaw = NULL;
		}

		parent::handleSave($form);
	}


	public function handleLoad(Form $form)
	{
		if (!$form->data->parent) {
			$form['mainRoute']['copyLayoutFromParent']->value = FALSE;
		}

		if ($form->data->navigationTitleRaw !== NULL) {
			$form['navigationOwn']->value = TRUE;
		}
	}


	public function handleCatchError(Form $form, $e)
	{
		if ($e instanceof \Nette\InvalidArgumentException) {
			$form->addError($e->getMessage());
			return TRUE;
		} else if ($e instanceof \Doctrine\DBAL\DBALException && strpos($e->getMessage(), 'Duplicate entry') !== false) {
			$form->addError('Duplicate entry');
			return TRUE;
		} elseif ($e instanceof \Exception) {
			$form->addError('Type: ' . get_class($e) . '. ' . $e->getMessage());
			return TRUE;
		}
	}
}
