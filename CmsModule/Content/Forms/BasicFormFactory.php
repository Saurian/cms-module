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

use Doctrine\Common\Collections\ArrayCollection;
use DoctrineModule\Forms\FormFactory;
use DoctrineModule\Repositories\BaseRepository;
use Venne\Forms\Form;

/**
 * @author Josef Kříž <pepakriz@gmail.com>
 */
class BasicFormFactory extends FormFactory
{

	/** @var \CmsModule\Pages\Tags\PageEntity */
	private $tagsPageEntity = FALSE;

	/** @var \CmsModule\Pages\Users\PageEntity */
	private $userPageEntity = FALSE;


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

		// route
		$page = $form->addOne('page');
		$mainRoute = $page->addOne('mainRoute');
		$mainRoute->setCurrentGroup($infoGroup);

		$name = $mainRoute->addText('name', 'Name');
		if (!$form->data->page->mainRoute->locale) {
			$name->addRule($form::FILLED);;
		}

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
			->addRule($form::REGEXP, "URL can not contain '/'", "/^[a-zA-z0-9._-]*$/");
		$mainRoute['localUrl']
			->setOption('description', $htmlUrl)
			->getControlPrototype()->class[] = 'localUrl';
		if (!$form->data->page->mainRoute->locale) {
			$mainRoute['localUrl']->addRule($form::FILLED);;
		}


		$mainRoute->setCurrentGroup($group = $form->addGroup());

		$mainRoute->addText('title', 'Title')
			->setOption('description', $htmlTitle)
			->getControlPrototype()->class[] = 'formTitle';

		$page->setCurrentGroup($group);
		if ($form->data->page->parent) {
			$page->addManyToOne('parent', 'Parent')->setPrompt(NULL);
		}

		if ($this->getUserPage()) {
			$mainRoute->addManyToOne('author', 'Author')->setDisabled(TRUE);
		}

		if ($this->getTagPage()) {
			$mainRoute->addTags('contentTags', 'Tags');
		}

		$mainRoute->setCurrentGroup($form->addGroup('Layout'));

		if ($form->data->page->parent) {
			$mainRoute->addCheckbox('copyLayoutFromParent', 'Layout from parent');
		} else {
			$mainRoute->addHidden('copyLayoutFromParent', 'Layout from parent');
		}
		$mainRoute['copyLayoutFromParent']->addCondition($form::EQUAL, FALSE)->toggle('group-layout_' . $form->data->id);

		if ($form->data->page->parent) {
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
		$page->setCurrentGroup($form->addGroup('Navigation'));
		$page->addCheckbox('navigationShow', 'Show in navigation')->addCondition($form::EQUAL, TRUE)->toggle('form-navigation-own');
		$page->setCurrentGroup($form->addGroup()->setOption('id', 'form-navigation-own'));
		$page->addCheckbox('navigationOwn', 'Use own title')->addCondition($form::EQUAL, TRUE)->toggle('form-navigation-title');
		$page->setCurrentGroup($form->addGroup()->setOption('id', 'form-navigation-title'));
		$page->addText('navigationTitleRaw', 'Navigation title');

		if (!$form->data->page->mainRoute->locale) {
			// languages
			/** @var $repository \DoctrineModule\Repositories\BaseRepository */
			$repository = $form->mapper->entityManager->getRepository('CmsModule\Content\Entities\LanguageEntity');
			if ($repository->createQueryBuilder('a')->select('COUNT(a)')->getQuery()->getSingleScalarResult() > 1) {
				$form->addGroup("Languages");
				$page->addManyToOne("language", "Content is in")
					->setPrompt('shared');
			}
		}

		$form->addGroup();
		$form->addSaveButton('Save');

		$js = '
        	function detectAuto() {
				$("#' . $form['page']['mainRoute']['name']->getHtmlId() . '").unbind("keyup keydown blur");
				$("#' . $form['page']['mainRoute']['name']->getHtmlId() . '").stringToSlug({getPut: ".localUrlTemplate", space: "-" });

				$("#' . $form['page']['mainRoute']['name']->getHtmlId() . '").trigger("keyup");

				if($("#form-checkbox").is(":checked")) {
		            $("#' . $form['page']['mainRoute']['name']->getHtmlId() . '").stringToSlug({getPut: ".localUrl", space: "-" });
				}

				if($("#form-checkbox-title").is(":checked")) {
		            $("#' . $form['page']['mainRoute']['name']->getHtmlId() . '").bind("keydown keyup blur", function(){
		            	$(".formTitle").val($(this).val());
		            });
				}
			}

			function detectCheckbox() {
			    if($("#' . $form['page']['mainRoute']['localUrlTemplate']->getHtmlId() . '").val() == $(".localUrl").val()){
					$("#form-checkbox").attr("checked", true);
				}

				if($("#' . $form['page']['mainRoute']['name']->getHtmlId() . '").val() == $(".formTitle").val()){
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
				    $("#' . $form['page']['mainRoute']['name']->getHtmlId() . '").trigger("keyup");
				});

				$("#form-checkbox-title").live("click", function(){
				    detectAuto();
				    $("#' . $form['page']['mainRoute']['name']->getHtmlId() . '").trigger("keyup");
				});
			});
		';

		$script = \Nette\Utils\Html::el('script');
		$script->setHtml($js);
		$form['page']['mainRoute']['name']->setOption('description', $script);
	}


	public function handleSave(Form $form)
	{
		if ($this->getTagPage()) {
			$repository = $this->getTagRepository();
			$collection = new ArrayCollection;
			foreach ($form['page']['mainRoute']['contentTags']->getValue() as $key => $tag) {
				if (!$tag) {
					continue;
				}
				if (($entity = $repository->findOneBy(array('name' => $tag))) === NULL) {
					$entity = $repository->createNew();
					$entity->setName($tag);
				}
				$collection[$key] = $entity;
			}
			$form->data->page->mainRoute->setTags($collection);
		}

		if ($form['page']['navigationOwn']->value) {
			$form->data->page->navigationTitleRaw = $form['navigationTitleRaw']->value;
		} else {
			$form->data->page->navigationTitleRaw = NULL;
		}

		parent::handleSave($form);
	}


	public function handleLoad(Form $form)
	{
		if ($this->getTagPage()) {
			$tags = array();
			foreach ($form->data->page->mainRoute->getTags() as $tag) {
				$tags[] = $tag->getName();
			}
			$form['page']['mainRoute']['contentTags']->setValue($tags);
		}

		if (!$form->data->page->parent) {
			$form['page']['mainRoute']['copyLayoutFromParent']->value = FALSE;
		}

		if ($form->data->page->navigationTitleRaw !== NULL) {
			$form['page']['navigationOwn']->value = TRUE;
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
		}
	}


	/**
	 * @return \Doctrine\ORM\EntityRepository
	 */
	private function getTagRepository()
	{
		return $this->mapper->getEntityManager()->getRepository('CmsModule\Pages\Tags\TagEntity');
	}


	/**
	 * @return \CmsModule\Pages\Tags\PageEntity
	 */
	private function getTagPage()
	{
		if ($this->tagsPageEntity === FALSE) {
			$this->tagsPageEntity = $this->mapper->getEntityManager()->getRepository('CmsModule\Pages\Tags\PageEntity')->findOneBy(array());
		}
		return $this->tagsPageEntity;
	}


	/**
	 * @return \CmsModule\Pages\Users\PageEntity
	 */
	private function getUserPage()
	{
		if ($this->userPageEntity === FALSE) {
			$this->userPageEntity = $this->mapper->getEntityManager()->getRepository('CmsModule\Pages\Users\PageEntity')->findOneBy(array());
		}
		return $this->userPageEntity;
	}
}
